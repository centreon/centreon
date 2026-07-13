<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Aggregate\Notification\Message;

use App\Shared\Domain\Aggregate\AggregateRoot;

class Message extends AggregateRoot {
public function __construct(
    ?MessageId $id,
    public readonly MessageChannelEnum $channel,
    public readonly MessageSubject $subject,
    public readonly string $message,
    public readonly string $formattedMessage,
)
{
    parent::__construct($id);
}
}
