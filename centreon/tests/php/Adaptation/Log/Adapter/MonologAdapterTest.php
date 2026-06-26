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

namespace Tests\Adaptation\Log\Adapter;

use Adaptation\Log\Adapter\MonologAdapter;
use Adaptation\Log\Channel\ModuleLogChannel;
use Adaptation\Log\Enum\LogChannelEnum;
use App\Shared\Infrastructure\Logging\SanitizingProcessor;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\WebProcessor;
use PHPUnit\Framework\TestCase;

final class MonologAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        // create() builds a StreamHandler under _CENTREON_LOG_; the stream is
        // opened lazily (only on write), so a temp dir is enough and merely
        // constructing the logger writes no file.
        if (! defined('_CENTREON_LOG_')) {
            define('_CENTREON_LOG_', sys_get_temp_dir() . '/');
        }
    }

    public function testSanitizingProcessorRunsLastAfterWebProcessor(): void
    {
        // The redaction processor must run AFTER WebProcessor fills `extra.url`.
        // Monolog applies processors in array order, so the sanitiser has to be
        // the LAST element — it is pushed first and pushProcessor is LIFO.
        $adapter = MonologAdapter::create(LogChannelEnum::WEB);

        $logger = (new \ReflectionProperty($adapter, 'logger'))->getValue($adapter);
        self::assertInstanceOf(Logger::class, $logger);

        $order = array_map(
            static fn (object $processor): string => $processor::class,
            $logger->getProcessors(),
        );

        self::assertContains(WebProcessor::class, $order);
        self::assertSame(SanitizingProcessor::class, $order[array_key_last($order)]);
        self::assertGreaterThan(
            array_search(WebProcessor::class, $order, true),
            array_search(SanitizingProcessor::class, $order, true),
        );
    }

    public function testModuleChannelWritesToItsHistoricalFileWithPlatformProcessors(): void
    {
        // A module channel must write to its literal historical file name (no
        // APP_ENV prefix) and still get the full platform processor stack.
        $adapter = MonologAdapter::create(new ModuleLogChannel('license-manager'));

        $logger = (new \ReflectionProperty($adapter, 'logger'))->getValue($adapter);
        self::assertInstanceOf(Logger::class, $logger);

        $handler = $logger->getHandlers()[0];
        self::assertInstanceOf(StreamHandler::class, $handler);
        self::assertStringEndsWith('/license-manager.log', (string) $handler->getUrl());

        $order = array_map(
            static fn (object $processor): string => $processor::class,
            $logger->getProcessors(),
        );
        self::assertContains(WebProcessor::class, $order);
        self::assertSame(SanitizingProcessor::class, $order[array_key_last($order)]);
    }
}
