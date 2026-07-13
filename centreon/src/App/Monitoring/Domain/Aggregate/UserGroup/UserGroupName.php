<?php

declare(strict_types=1);

namespace App\Monitoring\Domain\Aggregate\UserGroup;

use Webmozart\Assert\Assert;

class UserGroupName {
    public string $value;
    public const MIN_LENGTH = 1;
    public const MAX_LENGTH = 200;

    public function __construct(string $value)
    {
        $value = trim($value);
        Assert::lengthBetween($value, self::MIN_LENGTH, self::MAX_LENGTH);
        $this->value = $value;
    }
}
