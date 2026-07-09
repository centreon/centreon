<?php

declare(strict_types=1);

namespace App\Monitoring\Infrastructure\ApiPlatform\Resource\Notification;

use Symfony\Component\Validator\Constraints as Assert;

class CreateNotificationDto {
    public function __construct(
        #[Assert\Length(min: 1, max: 250, normalizer: 'trim')]
        public string $name,
        public int $timeperiodId,
        public bool $isActivated = true,
    )
    {
    }
}
