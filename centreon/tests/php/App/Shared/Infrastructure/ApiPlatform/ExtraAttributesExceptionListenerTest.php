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

namespace Tests\App\Shared\Infrastructure\ApiPlatform;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Shared\Infrastructure\ApiPlatform\ExtraAttributesExceptionListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;

final class ExtraAttributesExceptionListenerTest extends TestCase
{
    public function testItLeavesAnUnrelatedExceptionAlone(): void
    {
        $exception = new \RuntimeException('boom');

        self::assertSame($exception, $this->dispatch($exception));
    }

    public function testEachRefusedFieldBecomesAViolationOnThatField(): void
    {
        $throwable = $this->dispatch(new ExtraAttributesException(['checkOptions.macros[0].description', 'other']));

        self::assertInstanceOf(ValidationException::class, $throwable);
        $violations = $throwable->getConstraintViolationList();
        self::assertCount(2, $violations);
        self::assertSame('checkOptions.macros[0].description', $violations->get(0)->getPropertyPath());
        self::assertSame('This field is not allowed.', $violations->get(0)->getMessage());
        self::assertSame('other', $violations->get(1)->getPropertyPath());
    }

    private function dispatch(\Throwable $exception): \Throwable
    {
        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );

        new ExtraAttributesExceptionListener()($event);

        return $event->getThrowable();
    }
}
