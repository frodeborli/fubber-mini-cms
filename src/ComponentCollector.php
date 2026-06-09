<?php

namespace MiniCms;

class ComponentCollector
{
    private static ?self $instance = null;
    private bool $collecting = false;

    /** @var array<string, array<string, array>> Context groups: [contextLabel => [slug => data]] */
    private array $components = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function startCollecting(): void
    {
        $this->collecting = true;
        $this->components = [];
    }

    public function stopCollecting(): array
    {
        $this->collecting = false;
        $result = $this->components;
        $this->components = [];
        return $result;
    }

    public function isCollecting(): bool
    {
        return $this->collecting;
    }

    public function register(
        string $contextPath,
        string $contextLabel,
        string $slug,
        string $label,
        int $pos,
        string $type,
        string $default,
        mixed $value,
        array $meta = [],
    ): void {
        if (!$this->collecting) return;

        $this->components[$contextLabel][$slug] = [
            'context' => $contextPath,
            'slug' => $slug,
            'label' => $label,
            'pos' => $pos,
            'type' => $type,
            'default' => $default,
            'value' => $value,
        ] + $meta;
    }
}
