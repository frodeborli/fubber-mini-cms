<?php

namespace MiniCms\Ai;

class NullAgent implements AgentInterface
{
    public static function isAvailable(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'None';
    }

    public function submitPrompt(string $prompt): void
    {
    }

    public function stream(int $position = 0): \Generator
    {
        yield json_encode(['pos' => 0, 'msg' => ['type' => 'error', 'error' => 'No AI agent available']]) . "\n";
    }

    public function isProcessing(): bool
    {
        return false;
    }

    public function getHistory(): array
    {
        return [];
    }

    public function newConversation(): void
    {
    }

    public function hasSession(): bool
    {
        return false;
    }

    public function getLastPage(): ?string
    {
        return null;
    }

    public function setLastPage(?string $page): void
    {
    }
}
