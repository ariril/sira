<?php

namespace App\Support\Mail;

class LastMailSendStore
{
    private ?string $lastMessageId = null;
    private array $lastMeta = [];

    public function setLast(string $messageId, array $meta = []): void
    {
        $this->lastMessageId = $messageId;
        $this->lastMeta = $meta;
    }

    public function clear(): void
    {
        $this->lastMessageId = null;
        $this->lastMeta = [];
    }

    public function lastMessageId(): ?string
    {
        return $this->lastMessageId;
    }

    public function lastMeta(): array
    {
        return $this->lastMeta;
    }
}
