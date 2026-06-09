<?php

namespace MiniCms;

use Psr\Http\Message\StreamInterface;

class GeneratorStream implements StreamInterface
{
    private \Generator $generator;
    private string $buffer = '';
    private bool $finished = false;

    public function __construct(\Generator $generator)
    {
        $this->generator = $generator;
    }

    public function read(int $length): string
    {
        while (!$this->finished && strlen($this->buffer) < $length) {
            if (!$this->generator->valid()) {
                $this->finished = true;
                break;
            }
            $chunk = $this->generator->current();
            $this->generator->next();
            if ($chunk !== null && $chunk !== '') {
                $this->buffer .= $chunk;
            }
        }

        if ($this->buffer === '') {
            return '';
        }

        $data = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, $length);
        if ($this->buffer === false) {
            $this->buffer = '';
        }
        return $data;
    }

    public function eof(): bool
    {
        return $this->finished && $this->buffer === '';
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function getContents(): string
    {
        $result = '';
        while (!$this->eof()) {
            $result .= $this->read(65536);
        }
        return $result;
    }

    public function __toString(): string
    {
        return $this->getContents();
    }

    public function close(): void
    {
        $this->finished = true;
        $this->buffer = '';
    }

    public function detach()
    {
        $this->close();
        return null;
    }

    public function tell(): int
    {
        throw new \RuntimeException('GeneratorStream is not seekable');
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        throw new \RuntimeException('GeneratorStream is not seekable');
    }

    public function rewind(): void
    {
        throw new \RuntimeException('GeneratorStream is not seekable');
    }

    public function write($string): int
    {
        throw new \RuntimeException('GeneratorStream is not writable');
    }

    public function getMetadata($key = null)
    {
        return $key !== null ? null : [];
    }
}
