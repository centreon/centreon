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

namespace Tests\App\Shared\Infrastructure\Legacy;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Shared\Infrastructure\Legacy\LegacyValidationStatusListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Tests\App\Shared\Infrastructure\Legacy\Double\FakeHttpKernel;

final class LegacyValidationStatusListenerTest extends TestCase
{
    public function testItAnswers400OnTheLegacyPrefix(): void
    {
        $event = $this->createExceptionEvent(
            $this->createValidationException(),
            '/api/latest/configuration/pollers',
        );

        (new LegacyValidationStatusListener())($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame(
            ['code' => 400, 'message' => "[name] This value should not be blank.\n"],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testItLeavesTheBareApiPrefixToApiPlatform(): void
    {
        $event = $this->createExceptionEvent(
            $this->createValidationException(),
            '/api/configuration/pollers',
        );

        (new LegacyValidationStatusListener())($event);

        self::assertNull($event->getResponse());
    }

    public function testItIgnoresOtherExceptionsOnTheLegacyPrefix(): void
    {
        $event = $this->createExceptionEvent(
            new \RuntimeException('Something else'),
            '/api/latest/configuration/pollers',
        );

        (new LegacyValidationStatusListener())($event);

        self::assertNull($event->getResponse());
    }

    private function createValidationException(): ValidationException
    {
        return new ValidationException(new ConstraintViolationList([
            new ConstraintViolation(
                message: 'This value should not be blank.',
                messageTemplate: null,
                parameters: [],
                root: null,
                propertyPath: 'name',
                invalidValue: '',
            ),
        ]));
    }

    private function createExceptionEvent(\Throwable $throwable, string $path): ExceptionEvent
    {
        return new ExceptionEvent(
            new FakeHttpKernel(),
            Request::create($path),
            HttpKernelInterface::MAIN_REQUEST,
            $throwable,
        );
    }
}
