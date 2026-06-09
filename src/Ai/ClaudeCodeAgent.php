<?php

namespace MiniCms\Ai;

class ClaudeCodeAgent implements AgentInterface
{
    private string $projectDir;
    private string $claudeBin;
    private string $stateFile;
    private string $outputFile;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
        $this->claudeBin = getenv('CLAUDE_BIN') ?: (getenv('HOME') . '/.local/bin/claude');
        $this->stateFile = $projectDir . '/.cms/ai-state.json';
        $this->outputFile = $projectDir . '/.cms/ai-stream.ndjson';
    }

    public function submitPrompt(string $prompt): void
    {
        $this->ensureDir();

        $cmd = [$this->claudeBin, '-p', $prompt, '--output-format', 'stream-json', '--verbose'];

        $sessionId = $this->getSessionId();
        if ($sessionId) {
            $cmd[] = '--resume';
            $cmd[] = $sessionId;
        }

        $cmd[] = '--permission-mode';
        $cmd[] = 'auto';

        $escapedCmd = implode(' ', array_map('escapeshellarg', $cmd));

        file_put_contents($this->outputFile, '');

        $fullCmd = sprintf(
            'cd %s && %s > %s 2>&1 & echo $!',
            escapeshellarg($this->projectDir),
            $escapedCmd,
            escapeshellarg($this->outputFile)
        );

        $pid = (int) trim(shell_exec($fullCmd));

        $state = $this->loadState();
        $state['pid'] = $pid;
        $this->writeState($state);
    }

    public function stream(int $position = 0): \Generator
    {
        if (!is_file($this->outputFile)) {
            yield json_encode(['type' => 'error', 'message' => 'No active stream']) . "\n";
            return;
        }

        $handle = fopen($this->outputFile, 'r');
        if (!$handle) {
            yield json_encode(['type' => 'error', 'message' => 'Cannot open stream file']) . "\n";
            return;
        }

        if ($position > 0) {
            $size = filesize($this->outputFile);
            fseek($handle, $position <= $size ? $position : 0, SEEK_SET);
        }

        $idleCount = 0;
        $maxIdle = 600;

        while (true) {
            $line = fgets($handle);

            if ($line !== false && trim($line) !== '') {
                $trimmed = trim($line);
                $pos = ftell($handle);

                $data = json_decode($trimmed, true);
                if ($data) {
                    $this->captureSessionId($data);
                }

                yield json_encode(['pos' => $pos, 'msg' => $data]) . "\n";
                $idleCount = 0;
                continue;
            }

            fseek($handle, 0, SEEK_CUR);
            clearstatcache(false, $this->outputFile);

            if (!$this->isProcessing()) {
                while (($tail = fgets($handle)) !== false) {
                    if (trim($tail) === '') continue;
                    $trimmed = trim($tail);
                    $pos = ftell($handle);
                    $data = json_decode($trimmed, true);
                    if ($data) $this->captureSessionId($data);
                    yield json_encode(['pos' => $pos, 'msg' => $data]) . "\n";
                }
                break;
            }

            $idleCount++;
            if ($idleCount >= $maxIdle) {
                yield json_encode(['type' => 'error', 'message' => 'Stream timeout']) . "\n";
                break;
            }

            usleep(100000);
        }

        fclose($handle);
    }

    public function isProcessing(): bool
    {
        $state = $this->loadState();
        $pid = $state['pid'] ?? null;
        if (!$pid) {
            return false;
        }
        return posix_kill($pid, 0);
    }

    public function getHistory(): array
    {
        $sessionId = $this->getSessionId();
        if (!$sessionId) {
            return [];
        }

        $jsonlFile = $this->findSessionFile($sessionId);
        if (!$jsonlFile) {
            return [];
        }

        $messages = [];
        $handle = fopen($jsonlFile, 'r');
        if (!$handle) {
            return [];
        }

        $lastRole = null;
        while (($line = fgets($handle)) !== false) {
            $data = json_decode(trim($line), true);
            if (!$data) {
                continue;
            }

            $type = $data['type'] ?? '';
            if ($type !== 'user' && $type !== 'assistant') {
                continue;
            }

            $blocks = $this->extractContent($data);
            if (empty($blocks)) {
                continue;
            }

            if ($type === $lastRole && $messages) {
                $last = &$messages[count($messages) - 1];
                $last['content'] = array_merge($last['content'], $blocks);
            } else {
                $messages[] = ['role' => $type, 'content' => $blocks];
            }
            $lastRole = $type;
        }

        fclose($handle);

        if (count($messages) > 50) {
            $messages = array_slice($messages, -50);
        }

        return $messages;
    }

    public function newConversation(): void
    {
        $this->saveState(null, null);
        if (is_file($this->outputFile)) {
            unlink($this->outputFile);
        }
    }

    public function hasSession(): bool
    {
        return $this->getSessionId() !== null;
    }

    public function getLastPage(): ?string
    {
        $state = $this->loadState();
        return $state['last_page'] ?? null;
    }

    public function setLastPage(?string $page): void
    {
        $state = $this->loadState();
        $state['last_page'] = $page;
        $this->writeState($state);
    }

    private function extractContent(array $data): array
    {
        $rawContent = $data['message']['content'] ?? '';

        if (is_string($rawContent)) {
            $trimmed = trim($rawContent);
            return $trimmed !== '' ? [['type' => 'text', 'text' => $trimmed]] : [];
        }

        if (!is_array($rawContent)) {
            return [];
        }

        $blocks = [];
        foreach ($rawContent as $block) {
            $type = $block['type'] ?? '';
            if ($type === 'text' && !empty($block['text'])) {
                $blocks[] = ['type' => 'text', 'text' => $block['text']];
            } elseif ($type === 'tool_use') {
                $blocks[] = [
                    'type' => 'tool_use',
                    'name' => $block['name'] ?? 'tool',
                    'input' => $block['input'] ?? [],
                ];
            }
        }

        return $blocks;
    }

    private function captureSessionId(array $data): void
    {
        $type = $data['type'] ?? '';
        $sessionId = null;

        if ($type === 'system' && ($data['subtype'] ?? '') === 'init') {
            $sessionId = $data['session_id'] ?? null;
        } elseif ($type === 'result') {
            $sessionId = $data['session_id'] ?? null;
        }

        if ($sessionId) {
            $state = $this->loadState();
            $state['session_id'] = $sessionId;
            $this->writeState($state);
        }
    }

    private function getSessionId(): ?string
    {
        $state = $this->loadState();
        return $state['session_id'] ?? null;
    }

    private function saveState(?string $sessionId, ?string $lastPage): void
    {
        $this->writeState([
            'session_id' => $sessionId,
            'last_page' => $lastPage,
        ]);
    }

    private function loadState(): array
    {
        if (!is_file($this->stateFile)) {
            return [];
        }
        return json_decode(file_get_contents($this->stateFile), true) ?: [];
    }

    private function writeState(array $state): void
    {
        $this->ensureDir();
        file_put_contents($this->stateFile, json_encode($state, JSON_PRETTY_PRINT) . "\n");
    }

    private function ensureDir(): void
    {
        $dir = dirname($this->stateFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function findSessionFile(string $sessionId): ?string
    {
        $claudeDir = getenv('HOME') . '/.claude/projects';
        $projectSlug = str_replace('/', '-', $this->projectDir);
        $file = $claudeDir . '/' . $projectSlug . '/' . $sessionId . '.jsonl';

        if (is_file($file)) {
            return $file;
        }

        if (!is_dir($claudeDir)) {
            return null;
        }

        foreach (scandir($claudeDir) as $dir) {
            if ($dir[0] === '.') {
                continue;
            }
            $candidate = $claudeDir . '/' . $dir . '/' . $sessionId . '.jsonl';
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
