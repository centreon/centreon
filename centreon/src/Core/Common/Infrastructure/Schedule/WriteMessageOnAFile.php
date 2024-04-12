<?php

namespace Core\Common\Infrastructure\Schedule;

class WriteMessageOnAFile
{
    public function __construct(private readonly string $message)
    {

    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
