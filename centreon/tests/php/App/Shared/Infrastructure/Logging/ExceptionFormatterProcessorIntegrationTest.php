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

namespace Tests\App\Shared\Infrastructure\Logging;

use App\Shared\Infrastructure\Logging\ExceptionFormatterProcessor;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\Monolog\Processor\RouteProcessor;
use Symfony\Bridge\Monolog\Processor\TokenProcessor;
use Symfony\Bridge\Monolog\Processor\WebProcessor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class ExceptionFormatterProcessorIntegrationTest extends KernelTestCase
{
    /**
     * Pin the channel-scoped processor wiring on the platform Monolog
     * channels: `monolog.logger.bus`, `monolog.logger.request` and
     * `monolog.logger` (the `app` channel is served by the unsuffixed
     * default service) each carry the four platform processors. Drift on
     * the `#[AsMonologProcessor(channel: ...)]` attributes or on the
     * channel-tag list in `config.new/services/monolog.php` is what this
     * test catches. See doc/architecture/logging.md § "Processors HTTP /
     * sécurité".
     *
     * @return iterable<string, array{string}>
     */
    public static function platformChannels(): iterable
    {
        yield 'bus channel' => ['monolog.logger.bus'];

        yield 'request channel' => ['monolog.logger.request'];

        yield 'app channel (default)' => ['monolog.logger'];
    }

    #[DataProvider('platformChannels')]
    public function testChannelLoggerExposesAllExpectedProcessors(string $loggerServiceId): void
    {
        self::bootKernel();
        $logger = self::getContainer()->get($loggerServiceId);
        \assert($logger instanceof Logger);

        $processorClasses = [];
        foreach ($logger->getProcessors() as $processor) {
            if (\is_object($processor)) {
                $processorClasses[] = $processor::class;
            }
        }

        self::assertContains(ExceptionFormatterProcessor::class, $processorClasses);
        self::assertContains(WebProcessor::class, $processorClasses);
        self::assertContains(RouteProcessor::class, $processorClasses);
        self::assertContains(TokenProcessor::class, $processorClasses);
    }

    #[DataProvider('platformChannels')]
    public function testThrowableInContextIsReformattedOnTargetedChannel(string $loggerServiceId): void
    {
        // Behavioural pin on top of the wiring assertion: emit a record
        // that carries a raw Throwable in `context.exception` and verify
        // the channel-scoped ExceptionFormatterProcessor replaced it with
        // the structured array. Pushing a TestHandler on the logger lets
        // us inspect the post-processing record without touching the
        // file-based `web_file` handler.
        self::bootKernel();
        $logger = self::getContainer()->get($loggerServiceId);
        \assert($logger instanceof Logger);

        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        $logger->error('boom', ['exception' => new \RuntimeException('inner', 42)]);

        $records = $testHandler->getRecords();
        self::assertCount(1, $records);

        $exception = $records[0]->context['exception'];
        \assert(\is_array($exception));
        $exceptions = $exception['exceptions'];
        \assert(\is_array($exceptions));
        self::assertCount(1, $exceptions);
        $entry = $exceptions[0];
        \assert(\is_array($entry));
        self::assertSame(\RuntimeException::class, $entry['type']);
        self::assertSame('inner', $entry['message']);
        self::assertSame(42, $entry['code']);
    }
}
