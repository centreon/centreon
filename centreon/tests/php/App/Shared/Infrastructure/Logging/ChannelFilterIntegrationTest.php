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

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class ChannelFilterIntegrationTest extends KernelTestCase
{
    /**
     * Channels we want to land in prod.web.log — everything Symfony emits
     * by default that does not have a dedicated file in MON-151077. `app`
     * is the default Symfony channel served by the unsuffixed
     * `monolog.logger` service; covering it covers the common "no explicit
     * channel" case (Symfony's main channel).
     *
     * @return iterable<string, array{string}>
     */
    public static function capturedChannels(): iterable
    {
        yield 'bus channel' => ['monolog.logger.bus'];

        yield 'request channel' => ['monolog.logger.request'];

        yield 'app channel (default)' => ['monolog.logger'];
    }

    /**
     * Channels we explicitly excluded because MON-151077 routes them to
     * dedicated files (or because they are noisy/internal). A regression
     * on the exclusion list would silently flood prod.web.log.
     *
     * @return iterable<string, array{string}>
     */
    public static function excludedChannels(): iterable
    {
        yield 'event channel (Symfony)' => ['monolog.logger.event'];

        yield 'doctrine channel (DBAL)' => ['monolog.logger.doctrine'];

        yield 'console channel' => ['monolog.logger.console'];

        yield 'deprecation channel' => ['monolog.logger.deprecation'];

        yield 'authentication channel' => ['monolog.logger.authentication'];

        yield 'token channel' => ['monolog.logger.token'];
    }

    #[DataProvider('capturedChannels')]
    public function testCapturedChannelRoutesThroughWebFingersCrossed(string $loggerServiceId): void
    {
        // Pin "a log emitted on this channel transits through the
        // web_finger_crossed handler". Without this guard a typo in the
        // exclusion filter (e.g. "!main" by mistake) would silently keep
        // the legacy stderr behaviour and the operator would never see
        // these records in prod.web.log — exactly what MON-151077 fixes.
        self::bootKernel();
        $container = self::getContainer();

        $logger = $container->get($loggerServiceId);
        \assert($logger instanceof Logger);

        $webHandler = $container->get('monolog.handler.web_finger_crossed');
        \assert($webHandler instanceof HandlerInterface);

        self::assertContains(
            $webHandler,
            $logger->getHandlers(),
            sprintf('Logger %s must route records through web_finger_crossed', $loggerServiceId),
        );
    }

    #[DataProvider('excludedChannels')]
    public function testExcludedChannelDoesNotRouteThroughWebFingersCrossed(string $loggerServiceId): void
    {
        // Symfony Monolog only materialises a logger service when the
        // channel is declared in monolog.channels or used by an autowired
        // consumer. Some excluded channels may not exist at all in this
        // kernel — that is itself an exclusion, so we skip the assertion
        // rather than make the test brittle to the channel graph.
        self::bootKernel();
        $container = self::getContainer();

        if (! $container->has($loggerServiceId)) {
            self::markTestSkipped(sprintf('Logger service %s is not declared in this kernel — nothing to capture.', $loggerServiceId));
        }

        $logger = $container->get($loggerServiceId);
        \assert($logger instanceof Logger);

        $webHandler = $container->get('monolog.handler.web_finger_crossed');
        \assert($webHandler instanceof HandlerInterface);

        self::assertNotContains(
            $webHandler,
            $logger->getHandlers(),
            sprintf('Logger %s must NOT route records through web_finger_crossed (MON-151077 dedicated file)', $loggerServiceId),
        );
    }

    public function testWebFileFormatterUsesRfc3339DateFormat(): void
    {
        // Pin the line format used in prod.web.log. MON-151077 mandates
        // RFC3339 (e.g. 2025-09-08T15:38:41+02:00). Asserting the
        // configured date format directly is more deterministic than
        // serialising a record and parsing the wall-clock prefix.
        self::bootKernel();
        $container = self::getContainer();

        $handler = $container->get('monolog.handler.web_file');
        \assert($handler instanceof FormattableHandlerInterface);

        $formatter = $handler->getFormatter();
        self::assertInstanceOf(LineFormatter::class, $formatter);
        self::assertSame(\DateTimeInterface::RFC3339, $formatter->getDateFormat());
    }
}
