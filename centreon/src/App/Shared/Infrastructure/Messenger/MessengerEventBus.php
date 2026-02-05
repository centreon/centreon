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

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\Event\EventInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerEventBus implements EventBus
{
    public function __construct(
        private MessageBusInterface $eventBus,
    ) {
    }

    public function fire(EventInterface $event): void
    {
        try {
            $this->eventBus->dispatch($event);
        } catch (HandlerFailedException $e) {
            /** @var array{0: \Throwable} $exceptions */
            $exceptions = $e->getWrappedExceptions();

            throw current($exceptions);
        }
    }
}
