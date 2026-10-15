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
use App\Shared\Domain\Exception\AggregateAlreadyExistsException;
use App\Shared\Domain\Exception\AggregateConflictException;
use App\Shared\Domain\Exception\AggregateNotFoundException;
use App\Shared\Infrastructure\ApiPlatform\InvalidReferenceExceptionListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The exceptions here are anonymous subclasses of the two shared base classes: the listener keys
 * off those, and the concrete domain exceptions land with the endpoints that raise them.
 */
final class InvalidReferenceExceptionListenerTest extends TestCase
{
    public function testItLeavesAnUnrelatedExceptionAlone(): void
    {
        $exception = new \RuntimeException('boom');

        self::assertSame($exception, $this->dispatch($exception));
    }

    public function testACriterionNamingAPayloadFieldBecomesAViolationOnThatField(): void
    {
        $throwable = $this->dispatch($this->notFound(['reference' => 404], 'This reference does not exist.'));

        self::assertInstanceOf(ValidationException::class, $throwable);
        $violations = $throwable->getConstraintViolationList();
        self::assertCount(1, $violations);
        self::assertSame('reference', $violations->get(0)->getPropertyPath());
        self::assertSame('This reference does not exist.', $violations->get(0)->getMessage());
    }

    public function testEachPayloadFieldGetsItsOwnViolation(): void
    {
        $throwable = $this->dispatch($this->notFound(['reference' => 1, 'otherReference' => 2]));

        self::assertInstanceOf(ValidationException::class, $throwable);
        self::assertCount(2, $throwable->getConstraintViolationList());
    }

    public function testAConflictNamingAPayloadFieldIsAlsoConverted(): void
    {
        $exception = new class (['reference' => 7]) extends AggregateConflictException {};

        self::assertInstanceOf(ValidationException::class, $this->dispatch($exception));
    }

    /**
     * This is what keeps 404 alive for a missing target aggregate: `id` is not a payload field, so
     * the exception is left exactly as the domain threw it.
     */
    public function testACriterionThatIsNotAPayloadFieldIsLeftAlone(): void
    {
        $exception = $this->notFound(['id' => 42]);

        self::assertSame($exception, $this->dispatch($exception));
    }

    public function testAConflictNamingNoPayloadFieldKeepsItsStatus(): void
    {
        $exception = new class (['id' => 42]) extends AggregateConflictException {};

        self::assertSame($exception, $this->dispatch($exception));
    }

    /**
     * A value that is already taken conflicts with stored state rather than with the payload, so it
     * must keep its 409 even though the criterion names a payload field.
     */
    public function testAnAlreadyExistingAggregateKeepsItsConflictStatus(): void
    {
        $exception = new class (['reference' => 'taken']) extends AggregateAlreadyExistsException {};

        self::assertSame($exception, $this->dispatch($exception));
    }

    /**
     * PHP hands a numeric key back as an int, which property_exists() would reject outright.
     */
    public function testANumericCriterionIsLeftAlone(): void
    {
        // @phpstan-ignore argument.type (deliberately violating the array<string, mixed> contract)
        $exception = new class ([42 => 'whatever']) extends AggregateNotFoundException {};

        self::assertSame($exception, $this->dispatch($exception));
    }

    public function testAnOperationWithoutAnInputDtoIsLeftAlone(): void
    {
        $exception = $this->notFound(['reference' => 404]);

        self::assertSame($exception, $this->dispatch($exception, new Post()));
    }

    public function testKeepsTheDomainExceptionAsThePrevious(): void
    {
        $exception = $this->notFound(['reference' => 404]);

        self::assertSame($exception, $this->dispatch($exception)->getPrevious());
    }

    /**
     * @param array<string, mixed> $criteria
     */
    private function notFound(array $criteria, string $message = 'Not found.'): AggregateNotFoundException
    {
        return new class ($criteria, $message) extends AggregateNotFoundException {};
    }

    private function dispatch(\Throwable $exception, ?Post $operation = null): \Throwable
    {
        $request = new Request();
        $request->attributes->set(
            '_api_operation',
            $operation ?? new Post(input: InvalidReferenceExceptionListenerTestInput::class),
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

/**
 * Stands in for an operation's input DTO: only its property names matter to the listener.
 */
final readonly class InvalidReferenceExceptionListenerTestInput
{
    public function __construct(
        public int $reference = 0,
        public int $otherReference = 0,
    ) {
    }
}
