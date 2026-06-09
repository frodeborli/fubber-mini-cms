<?php

namespace MiniCms\Ai;

interface AgentInterface
{
    /**
     * Submit a prompt for processing.
     * The agent may launch a background process or begin immediately.
     */
    public function submitPrompt(string $prompt): void;

    /**
     * Stream the current response. Yields NDJSON lines wrapped as {"pos": int, "msg": object}.
     * The pos value is opaque — the client stores it and sends it back on reconnect.
     */
    public function stream(int $position = 0): \Generator;

    /**
     * Whether the agent is currently processing a prompt.
     */
    public function isProcessing(): bool;

    /**
     * Get conversation history.
     * Returns array of ['role' => 'user'|'assistant', 'content' => string].
     */
    public function getHistory(): array;

    /**
     * Start a new conversation, discarding the current session.
     */
    public function newConversation(): void;

    /**
     * Whether an active session exists.
     */
    public function hasSession(): bool;
}
