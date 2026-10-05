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

namespace Tests\App\Shared\Infrastructure\Messenger;

use App\Shared\Domain\Event\DeliveredAfterCommitInterface;
use App\Shared\Domain\Event\EventInterface;
use App\Shared\Infrastructure\Messenger\MessengerEventBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Tests\App\Shared\Infrastructure\Messenger\Double\RecordingMessageBus;

final class MessengerEventBusTest extends TestCase
{
    private RecordingMessageBus $messageBus;

    private MessengerEventBus $eventBus;

    protected function setUp(): void
    {
        $this->messageBus = new RecordingMessageBus();
        $this->eventBus = new MessengerEventBus($this->messageBus);
    }

    public function testAnOrdinaryEventIsDispatchedWithoutTheDeferringStamp(): void
    {
        $this->eventBus->fire($this->event());

        self::assertNull($this->messageBus->dispatched?->last(DispatchAfterCurrentBusStamp::class));
    }

    public function testAMarkedEventIsDispatchedWithTheDeferringStamp(): void
    {
        $this->eventBus->fire($this->deferredEvent());

        self::assertNotNull($this->messageBus->dispatched?->last(DispatchAfterCurrentBusStamp::class));
    }

    public function testItRethrowsTheDomainExceptionBehindAHandlerFailure(): void
    {
        $domainException = new \RuntimeException('handler blew up');
        $this->messageBus->throwOnDispatch = new HandlerFailedException(
            new Envelope($this->event()),
            [$domainException],
        );

        $this->expectExceptionObject($domainException);

        $this->eventBus->fire($this->event());
    }

    public function testItRethrowsTheDomainExceptionBehindADeferredHandlerFailure(): void
    {
        $domainException = new \RuntimeException('deferred handler blew up');
        $this->messageBus->throwOnDispatch = new DelayedMessageHandlingException(
            [$domainException],
            new Envelope($this->deferredEvent()),
        );

        $this->expectExceptionObject($domainException);

        $this->eventBus->fire($this->deferredEvent());
    }

    private function event(): EventInterface
    {
        return new class () implements EventInterface {
            public function firedAt(): \DateTimeImmutable
            {
                return new \DateTimeImmutable();
            }
        };
    }

    private function deferredEvent(): EventInterface
    {
        return new class () implements DeliveredAfterCommitInterface, EventInterface {
            public function firedAt(): \DateTimeImmutable
            {
                return new \DateTimeImmutable();
            }
        };
    }
}
