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

use ApiPlatform\Metadata\Post;
use ApiPlatform\Validator\Exception\ValidationException;
use App\MonitoringConfiguration\Domain\Exception\CircularHostRelationException;
use App\MonitoringConfiguration\Domain\Exception\HostAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\PollerNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimezoneNotFoundException;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreateHostInput;
use App\Shared\Domain\Exception\AggregateConflictException;
use App\Shared\Domain\Exception\AggregateNotFoundException;
use App\Shared\Infrastructure\ApiPlatform\InvalidReferenceExceptionListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class InvalidReferenceExceptionListenerTest extends TestCase
{
    public function testItLeavesAnUnrelatedExceptionAlone(): void
    {
        $exception = new \RuntimeException('boom');

        self::assertSame($exception, $this->dispatch($exception));
    }

    public function testACriterionNamingAPayloadFieldBecomesAViolationOnThatField(): void
    {
        $throwable = $this->dispatch(TimezoneNotFoundException::forId(404));

        self::assertInstanceOf(ValidationException::class, $throwable);
        $violations = $throwable->getConstraintViolationList();
        self::assertCount(1, $violations);
        self::assertSame('timezoneId', $violations->get(0)->getPropertyPath());
        self::assertSame('This timezone does not exist.', $violations->get(0)->getMessage());
    }

    public function testItReportsRelatedHostsAgainstTheSideTheyCameFrom(): void
    {
        $throwable = $this->dispatch(HostNotFoundException::forIds([404], 'childHostIds'));

        self::assertInstanceOf(ValidationException::class, $throwable);
        self::assertSame('childHostIds', $throwable->getConstraintViolationList()->get(0)->getPropertyPath());
    }

    public function testAConflictNamingAPayloadFieldIsAlsoConverted(): void
    {
        $throwable = $this->dispatch(CircularHostRelationException::forIds([7]));

        self::assertInstanceOf(ValidationException::class, $throwable);
        self::assertSame('parentHostIds', $throwable->getConstraintViolationList()->get(0)->getPropertyPath());
    }

    public function testACriterionThatIsNotAPayloadFieldIsLeftAlone(): void
    {
        $exception = new PollerNotFoundException(['id' => 42]);

        self::assertSame($exception, $this->dispatch($exception));
    }

    public function testAConflictNamingNoPayloadFieldKeepsItsStatus(): void
    {
        $exception = new class (['id' => 42]) extends AggregateConflictException {};

        self::assertSame($exception, $this->dispatch($exception));
    }

    public function testAnAlreadyExistingAggregateKeepsItsConflictStatus(): void
    {
        $exception = new HostAlreadyExistsException(['name' => 'srv-01']);

        self::assertSame($exception, $this->dispatch($exception));
    }

    public function testANumericCriterionIsLeftAlone(): void
    {
        // @phpstan-ignore argument.type (deliberately violating the array<string, mixed> contract)
        $exception = new class ([42 => 'whatever']) extends AggregateNotFoundException {};

        self::assertSame($exception, $this->dispatch($exception));
    }

    public function testAnOperationWithoutAnInputDtoIsLeftAlone(): void
    {
        $exception = TimezoneNotFoundException::forId(404);

        self::assertSame($exception, $this->dispatch($exception, new Post()));
    }

    public function testKeepsTheDomainExceptionAsThePrevious(): void
    {
        $exception = TimezoneNotFoundException::forId(404);

        self::assertSame($exception, $this->dispatch($exception)->getPrevious());
    }

    private function dispatch(\Throwable $exception, ?Post $operation = null): \Throwable
    {
        $request = new Request();
        $request->attributes->set(
            '_api_operation',
            $operation ?? new Post(input: CreateHostInput::class),
        );

        $event = new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );

        new InvalidReferenceExceptionListener()($event);

        return $event->getThrowable();
    }
}
