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
use App\Shared\Infrastructure\Legacy\LegacyHttpExceptionListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tests\App\Shared\Infrastructure\Legacy\Double\FakeHttpKernel;
use Tests\App\Shared\Infrastructure\Legacy\Double\RecordingLogger;

final class LegacyHttpExceptionListenerTest extends TestCase
{
    public function testBadRequestIsAnsweredAsAClientError(): void
    {
        $logger = new RecordingLogger();
        $event = $this->createExceptionEvent(
            new BadRequestHttpException('The parameter "page" must be an integer', code: Response::HTTP_BAD_REQUEST)
        );

        (new LegacyHttpExceptionListener([], $logger))($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(
            ['code' => Response::HTTP_BAD_REQUEST, 'message' => 'The parameter "page" must be an integer'],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)
        );
        $record = $logger->lastRecord();
        self::assertNotNull($record);
        self::assertSame(LogLevel::WARNING, $record['level']);
    }

    public function testExceptionWithoutHttpStatusIsAnsweredAsAServerError(): void
    {
        $logger = new RecordingLogger();
        $event = $this->createExceptionEvent(new \RuntimeException('Something went wrong'));

        (new LegacyHttpExceptionListener([], $logger))($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $record = $logger->lastRecord();
        self::assertNotNull($record);
        self::assertSame(LogLevel::CRITICAL, $record['level']);
    }

    public function testStatusIsReadFromTheApiPlatformMapping(): void
    {
        $logger = new RecordingLogger();
        $event = $this->createExceptionEvent(new \RuntimeException('Nothing here'));

        (new LegacyHttpExceptionListener([\RuntimeException::class => Response::HTTP_NOT_FOUND], $logger))($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $record = $logger->lastRecord();
        self::assertNotNull($record);
        self::assertSame(LogLevel::WARNING, $record['level']);
    }

    public function testValidationExceptionIsLeftToItsOwnNormalizer(): void
    {
        $logger = new RecordingLogger();
        $event = $this->createExceptionEvent(new ValidationException());

        (new LegacyHttpExceptionListener([], $logger))($event);

        self::assertNull($event->getResponse());
        self::assertSame([], $logger->records());
    }

    private function createExceptionEvent(\Throwable $throwable): ExceptionEvent
    {
        return new ExceptionEvent(
            new FakeHttpKernel(),
            Request::create('/api/latest/monitoring/resources'),
            HttpKernelInterface::MAIN_REQUEST,
            $throwable
        );
    }
}
