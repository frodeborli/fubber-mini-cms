<?php

namespace MiniCms\Ai;

class GeminiAgent implements AgentInterface
{
    private string $projectDir;
    private string $geminiBin;
    private string $stateFile;
    private string $outputFile;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
        $this->geminiBin = self::findBinary() ?? '';
        $this->stateFile = $projectDir . '/.cms/ai-state.json';
        $this->outputFile = $projectDir . '/.cms/ai-stream.ndjson';
    }

    public static function isAvailable(): bool
    {
        return self::findBinary() !== null;
    }

    public function getName(): string
    {
        return 'Gemini';
    }

    public function submitPrompt(string $prompt): void
    {
        $this->ensureDir();

        $cmd = [
            $this->geminiBin,
            '-p', $prompt,
            '-o', 'stream-json',
            '--skip-trust',
            '-y',
        ];

        $sessionId = $this->getSessionId();
        if ($sessionId) {
            $cmd[] = '--resume';
            $cmd[] = $sessionId;
        }

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

        $acc = ['text' => '', 'tools' => [], 'msgId' => 'gm-1', 'counter' => 1, 'afterToolResult' => false];

        // On reconnect, rebuild accumulated state by reading from the start
        if ($position > 0) {
            while (ftell($handle) < $position) {
                $line = fgets($handle);
                if ($line === false) break;
                $data = json_decode(trim($line), true);
                if ($data) {
                    $this->accumulate($data, $acc);
                }
            }
            fseek($handle, $position);
        }

        $idleCount = 0;
        $maxIdle = 600;

        while (true) {
            $line = fgets($handle);

            if ($line !== false && trim($line) !== '') {
                $data = json_decode(trim($line), true);
                $pos = ftell($handle);

                if (!$data) {
                    $idleCount = 0;
                    continue;
                }

                $this->captureSessionId($data);
                $this->accumulate($data, $acc);
                $normalized = $this->normalize($data, $acc);

                if ($normalized) {
                    yield json_encode(['pos' => $pos, 'msg' => $normalized]) . "\n";
                }

                $idleCount = 0;
                continue;
            }

            fseek($handle, 0, SEEK_CUR);
            clearstatcache(false, $this->outputFile);

            if (!$this->isProcessing()) {
                while (($tail = fgets($handle)) !== false) {
                    if (trim($tail) === '') continue;
                    $data = json_decode(trim($tail), true);
                    $pos = ftell($handle);
                    if (!$data) continue;
                    $this->captureSessionId($data);
                    $this->accumulate($data, $acc);
                    $normalized = $this->normalize($data, $acc);
                    if ($normalized) {
                        yield json_encode(['pos' => $pos, 'msg' => $normalized]) . "\n";
                    }
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
        if (!$pid) return false;
        return posix_kill($pid, 0);
    }

    public function getHistory(): array
    {
        $sessionId = $this->getSessionId();
        if (!$sessionId) return [];

        $jsonlFile = $this->findSessionFile($sessionId);
        if (!$jsonlFile) return [];

        $handle = fopen($jsonlFile, 'r');
        if (!$handle) return [];

        $messages = [];
        $seenGeminiIds = [];

        while (($line = fgets($handle)) !== false) {
            $data = json_decode(trim($line), true);
            if (!$data) continue;
            if (isset($data['$set']) || isset($data['sessionId'])) continue;

            $type = $data['type'] ?? '';

            if ($type === 'user') {
                $content = $data['content'] ?? [];
                if (!is_array($content)) continue;

                $texts = [];
                foreach ($content as $block) {
                    if (isset($block['functionResponse'])) continue;
                    $text = $block['text'] ?? '';
                    if ($text !== '' && !str_contains($text, '<session_context>')) {
                        $texts[] = $text;
                    }
                }
                if (empty($texts)) continue;

                $blocks = [];
                foreach ($texts as $t) {
                    $blocks[] = ['type' => 'text', 'text' => $t];
                }
                $messages[] = ['role' => 'user', 'content' => $blocks];
            }

            if ($type === 'gemini') {
                $id = $data['id'] ?? '';
                $msg = $this->parseGeminiHistoryMessage($data);
                if (!$msg) continue;

                if ($id && isset($seenGeminiIds[$id])) {
                    $messages[$seenGeminiIds[$id]] = $msg;
                } else {
                    $seenGeminiIds[$id] = count($messages);
                    $messages[] = $msg;
                }
            }
        }

        fclose($handle);

        $merged = [];
        foreach ($messages as $msg) {
            if ($merged && $merged[count($merged) - 1]['role'] === $msg['role']) {
                $merged[count($merged) - 1]['content'] = array_merge(
                    $merged[count($merged) - 1]['content'],
                    $msg['content']
                );
            } else {
                $merged[] = $msg;
            }
        }

        if (count($merged) > 50) {
            $merged = array_slice($merged, -50);
        }

        return $merged;
    }

    public function newConversation(): void
    {
        $state = $this->loadState();
        $state['gemini_session_id'] = null;
        $state['last_page'] = null;
        $this->writeState($state);
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
        return $this->loadState()['last_page'] ?? null;
    }

    public function setLastPage(?string $page): void
    {
        $state = $this->loadState();
        $state['last_page'] = $page;
        $this->writeState($state);
    }

    // -- Stream normalization --
    // Gemini emits deltas; Claude emits accumulated snapshots.
    // We accumulate text + tool_uses and emit Claude-format assistant events.

    private function accumulate(array $data, array &$acc): void
    {
        $type = $data['type'] ?? '';

        if ($type === 'message' && ($data['role'] ?? '') === 'assistant') {
            if ($acc['afterToolResult']) {
                $acc['counter']++;
                $acc['msgId'] = 'gm-' . $acc['counter'];
                $acc['text'] = '';
                $acc['tools'] = [];
                $acc['afterToolResult'] = false;
            }
            $acc['text'] .= $data['content'] ?? '';
        } elseif ($type === 'tool_use') {
            $name = $data['tool_name'] ?? '';
            if ($name === 'update_topic') return;
            $acc['tools'][] = [
                'type' => 'tool_use',
                'name' => $name,
                'id' => $data['tool_id'] ?? '',
                'input' => $data['parameters'] ?? [],
            ];
        } elseif ($type === 'tool_result') {
            $acc['afterToolResult'] = true;
        }
    }

    private function normalize(array $data, array $acc): ?array
    {
        $type = $data['type'] ?? '';

        if ($type === 'init') {
            return [
                'type' => 'system',
                'subtype' => 'init',
                'session_id' => $data['session_id'] ?? null,
            ];
        }

        if ($type === 'message' && ($data['role'] ?? '') === 'assistant') {
            return $this->buildAssistantEvent($acc);
        }

        if ($type === 'tool_use') {
            if (($data['tool_name'] ?? '') === 'update_topic') return null;
            return $this->buildAssistantEvent($acc);
        }

        if ($type === 'tool_result') {
            $toolId = $data['tool_id'] ?? '';
            if (str_starts_with($toolId, 'update_topic')) return null;
            return [
                'type' => 'user',
                'message' => [
                    'content' => [[
                        'type' => 'tool_result',
                        'tool_use_id' => $toolId,
                        'is_error' => ($data['status'] ?? '') === 'error',
                    ]],
                ],
            ];
        }

        if ($type === 'result') {
            return ['type' => 'result', 'session_id' => $data['session_id'] ?? null];
        }

        if ($type === 'error') {
            return ['type' => 'error', 'message' => $data['message'] ?? 'Unknown error'];
        }

        return null;
    }

    private function buildAssistantEvent(array $acc): array
    {
        $content = [];
        if ($acc['text'] !== '') {
            $content[] = ['type' => 'text', 'text' => $acc['text']];
        }
        foreach ($acc['tools'] as $t) {
            $content[] = $t;
        }
        return [
            'type' => 'assistant',
            'message' => ['id' => $acc['msgId'], 'content' => $content],
        ];
    }

    // -- History --

    private function parseGeminiHistoryMessage(array $data): ?array
    {
        $blocks = [];
        $text = $data['content'] ?? '';
        if ($text !== '') {
            $blocks[] = ['type' => 'text', 'text' => $text];
        }

        foreach ($data['toolCalls'] ?? [] as $tc) {
            $name = $tc['name'] ?? '';
            if ($name === 'update_topic') continue;
            $blocks[] = [
                'type' => 'tool_use',
                'name' => $name,
                'input' => $tc['args'] ?? [],
            ];
        }

        return $blocks ? ['role' => 'assistant', 'content' => $blocks] : null;
    }

    // -- Session / state --

    private function captureSessionId(array $data): void
    {
        $type = $data['type'] ?? '';
        $sessionId = null;

        if ($type === 'init') {
            $sessionId = $data['session_id'] ?? null;
        } elseif ($type === 'result') {
            $sessionId = $data['session_id'] ?? null;
        }

        if ($sessionId) {
            $state = $this->loadState();
            $state['gemini_session_id'] = $sessionId;
            $this->writeState($state);
        }
    }

    private function getSessionId(): ?string
    {
        return $this->loadState()['gemini_session_id'] ?? null;
    }

    private function loadState(): array
    {
        if (!is_file($this->stateFile)) return [];
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
        if (!is_dir($dir)) mkdir($dir, 0755, true);
    }

    private function findSessionFile(string $sessionId): ?string
    {
        $prefix = substr($sessionId, 0, 8);
        $projectName = basename($this->projectDir);
        $chatsDir = getenv('HOME') . '/.gemini/tmp/' . $projectName . '/chats';

        if (!is_dir($chatsDir)) return null;

        $pattern = $chatsDir . '/session-*-' . $prefix . '.jsonl';
        $files = glob($pattern);
        if ($files) return $files[0];

        foreach (scandir($chatsDir) as $file) {
            if (!str_ends_with($file, '.jsonl')) continue;
            $path = $chatsDir . '/' . $file;
            $fh = fopen($path, 'r');
            if (!$fh) continue;
            $firstLine = fgets($fh);
            fclose($fh);
            if ($firstLine) {
                $header = json_decode(trim($firstLine), true);
                if (($header['sessionId'] ?? '') === $sessionId) {
                    return $path;
                }
            }
        }

        return null;
    }

    private static function findBinary(): ?string
    {
        $envBin = getenv('GEMINI_BIN');
        if ($envBin && is_file($envBin) && is_executable($envBin)) {
            return $envBin;
        }

        $home = getenv('HOME');
        $candidates = [
            $home . '/.local/bin/gemini',
            $home . '/.npm-global/bin/gemini',
            '/usr/local/bin/gemini',
            '/usr/bin/gemini',
        ];

        $nvmBin = getenv('NVM_BIN');
        if ($nvmBin) {
            array_unshift($candidates, $nvmBin . '/gemini');
        }

        foreach ($candidates as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        $which = trim(shell_exec('which gemini 2>/dev/null') ?: '');
        if ($which && is_file($which) && is_executable($which)) {
            return $which;
        }

        return null;
    }
}
