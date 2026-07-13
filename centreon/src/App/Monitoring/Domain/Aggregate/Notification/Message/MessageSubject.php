<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Aggregate\Notification\Message;

use Webmozart\Assert\Assert;

final readonly class MessageSubject {
    public const MIN_LENGTH = 1;
    public const MAX_LENGTH = 255;

    public string $value;

    public function __construct(
        string $value,
    ) {
        $value = trim($value);
        Assert::lengthBetween($value, self::MIN_LENGTH, self::MAX_LENGTH);
        $this->value = $value;
    }
}
