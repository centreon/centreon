<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace App\Monitoring\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Monitoring\Domain\Aggregate\Notification\Notification;
use App\Monitoring\Domain\Aggregate\Notification\NotificationId;
use App\Monitoring\Domain\Exception\NotificationNotFoundException;
use App\Monitoring\Domain\Repository\NotificationRepository;
use App\Monitoring\Infrastructure\ApiPlatform\Resource\Notification\NotificationResource;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @implements ProviderInterface<NotificationResource>
 */
final readonly class FindNotificationProvider implements ProviderInterface
{
    /**
     * @param NotificationRepository $notificationRepository
     * @param TransformerInterface<Notification, NotificationResource> $transformer
     */
    public function __construct(
        private NotificationRepository $notificationRepository,
        #[Autowire(service: NotificationResourceTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    /**
     * @param array<array<string, mixed>> $uriVariables
     *
     * @throws NotificationNotFoundException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): NotificationResource
    {
        Assert::integer($uriVariables['id']);

        $notification = $this->notificationRepository->get(
            new NotificationId($uriVariables['id'])
        );

        return $this->transformer->transform($notification);
    }
}
