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

use App\Shared\Infrastructure\Messenger\MessengerCommandBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class MessengerCommandBusTest extends TestCase
{
    public function testItReturnsWhatTheHandlerProduced(): void
    {
        $result = new \stdClass();

        self::assertSame($result, new MessengerCommandBus($this->busReturning($result))->execute(new \stdClass()));
    }

    public function testItRethrowsTheDomainExceptionBehindAHandlerFailure(): void
    {
        $domainException = new \RuntimeException('handler blew up');
        $bus = $this->busThrowing(new HandlerFailedException(new Envelope(new \stdClass()), [$domainException]));

        $this->expectExceptionObject($domainException);

        new MessengerCommandBus($bus)->execute(new \stdClass());
    }

    public function testItRethrowsTheDomainExceptionBehindADeferredHandlerFailure(): void
    {
        $domainException = new \RuntimeException('deferred handler blew up');
        $bus = $this->busThrowing(new DelayedMessageHandlingException([$domainException], new Envelope(new \stdClass())));

        $this->expectExceptionObject($domainException);

        new MessengerCommandBus($bus)->execute(new \stdClass());
    }

    private function busReturning(object $result): MessageBusInterface
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(
            static fn (object $message): Envelope => new Envelope($message, [new HandledStamp($result, 'handler')]),
        );

        return $bus;
    }

    private function busThrowing(\Throwable $exception): MessageBusInterface
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException($exception);

        return $bus;
    }
}
