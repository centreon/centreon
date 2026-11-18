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
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\FingersCrossedHandler;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\Monolog\Handler\FingersCrossed\HttpCodeActivationStrategy;
use Symfony\Bridge\Monolog\Processor\RouteProcessor;
use Symfony\Bridge\Monolog\Processor\TokenProcessor;
use Symfony\Bridge\Monolog\Processor\WebProcessor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class ChannelFilterIntegrationTest extends KernelTestCase
{
    /**
     * Channels we want to land in prod.web.log — everything Symfony emits
     * by default that does not have a dedicated file. `app`
     * is the default Symfony channel served by the unsuffixed
     * `monolog.logger` service; covering it covers the common "no explicit
     * channel" case (Symfony's main channel).
     *
     * @return iterable<string, array{string}>
     */
    public static function capturedChannels(): iterable
    {
        yield 'request channel' => ['monolog.logger.request'];

        yield 'app channel (default)' => ['monolog.logger'];
    }

    /**
     * Channels we explicitly excluded because they are routed to
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

        yield 'upgrade channel' => ['monolog.logger.upgrade'];

        yield 'plugin-pack-manager channel' => ['monolog.logger.plugin-pack-manager'];

        yield 'poller-install channel' => ['monolog.logger.poller-install'];
    }

    #[DataProvider('capturedChannels')]
    public function testCapturedChannelRoutesThroughWebFingersCrossed(string $loggerServiceId): void
    {
        // Pin "a log emitted on this channel transits through the
        // web_finger_crossed handler". Without this guard a typo in the
        // exclusion filter (e.g. "!main" by mistake) would silently keep
        // the legacy stderr behaviour and the operator would never see
        // these records in prod.web.log.
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
            sprintf('Logger %s must NOT route records through web_finger_crossed (dedicated file)', $loggerServiceId),
        );
    }

    public function testWebFileFormatterUsesRfc3339DateFormat(): void
    {
        // Pin the line format used in prod.web.log: RFC3339
        // (e.g. 2025-09-08T15:38:41+02:00). Asserting the
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function everyChannel(): iterable
    {
        // Cross-channel set: the request/app pair gets the HTTP processors
        // via channel-scoped tags, but UidProcessor is declared globally —
        // so it must land on every channel logger including the dedicated ones.
        yield 'request channel' => ['monolog.logger.request'];

        yield 'app channel (default)' => ['monolog.logger'];

        yield 'deprecation channel' => ['monolog.logger.deprecation'];

        yield 'authentication channel' => ['monolog.logger.authentication'];

        yield 'token channel' => ['monolog.logger.token'];

        yield 'upgrade channel' => ['monolog.logger.upgrade'];

        yield 'plugin-pack-manager channel' => ['monolog.logger.plugin-pack-manager'];

        yield 'poller-install channel' => ['monolog.logger.poller-install'];
    }

    #[DataProvider('everyChannel')]
    public function testPlatformProcessorsAreStampedOnEveryChannel(string $loggerServiceId): void
    {
        // Pin "the four platform processors are registered on every channel
        // logger". They are declared in config.new/services/shared.php with
        // no channel tag (and ExceptionFormatterProcessor via #[AsMonologProcessor]
        // without channel:), which means MonologBundle attaches each of them
        // to every logger. This test guards against an accidental channel-
        // scoped tag landing on any of these services in the future.
        self::bootKernel();
        $container = self::getContainer();

        if (! $container->has($loggerServiceId)) {
            self::markTestSkipped(sprintf('Logger service %s is not declared in this kernel.', $loggerServiceId));
        }

        $logger = $container->get($loggerServiceId);
        \assert($logger instanceof Logger);

        $processorClasses = [];
        foreach ($logger->getProcessors() as $processor) {
            if (\is_object($processor)) {
                $processorClasses[] = $processor::class;
            }
        }

        foreach ([UidProcessor::class, ExceptionFormatterProcessor::class, WebProcessor::class, RouteProcessor::class, TokenProcessor::class] as $expected) {
            self::assertContains(
                $expected,
                $processorClasses,
                sprintf('Logger %s must expose %s (registered globally)', $loggerServiceId, $expected),
            );
        }
    }

    public function testUidProcessorStampsTheSameValueAcrossChannels(): void
    {
        // Behavioural pin on top of the wiring assertion: two records
        // emitted on two different channels during the same process must
        // carry the same `extra.uid`. That is the whole point — being
        // able to `grep <uid>` and reconstruct an entire request across
        // prod.web.log + prod.access.log + prod.token.log etc.
        self::bootKernel();
        $container = self::getContainer();

        $requestLogger = $container->get('monolog.logger.request');
        \assert($requestLogger instanceof Logger);
        $requestTestHandler = new TestHandler();
        $requestLogger->pushHandler($requestTestHandler);

        $appLogger = $container->get('monolog.logger');
        \assert($appLogger instanceof Logger);
        $appTestHandler = new TestHandler();
        $appLogger->pushHandler($appTestHandler);

        $requestLogger->info('from request');
        $appLogger->info('from app');

        self::assertCount(1, $requestTestHandler->getRecords());
        self::assertCount(1, $appTestHandler->getRecords());
        $requestRecord = $requestTestHandler->getRecords()[0];
        $appRecord = $appTestHandler->getRecords()[0];

        self::assertArrayHasKey('uid', $requestRecord->extra);
        self::assertArrayHasKey('uid', $appRecord->extra);
        self::assertSame(
            $requestRecord->extra['uid'],
            $appRecord->extra['uid'],
            'Records emitted in the same process on different channels must share the same UidProcessor uid',
        );
    }

    public function testWebFingerCrossedExcludesHttp404And405(): void
    {
        // Pin "404/405 do not trigger the fingers_crossed buffer flush"
        // — that is the Symfony recipe pattern (excluded_http_codes) we
        // adopted to prevent bot scans on /wp-admin, /.env etc. from
        // flooding prod.web.log. Verified by inspecting the activation
        // strategy on the configured handler.
        self::bootKernel();
        $container = self::getContainer();

        $handler = $container->get('monolog.handler.web_finger_crossed');
        \assert($handler instanceof FingersCrossedHandler);

        $strategy = new \ReflectionProperty(FingersCrossedHandler::class, 'activationStrategy')
            ->getValue($handler);
        self::assertInstanceOf(HttpCodeActivationStrategy::class, $strategy);

        $excludedHttpCodes = new \ReflectionProperty(HttpCodeActivationStrategy::class, 'exclusions')
            ->getValue($strategy);
        \assert(\is_array($excludedHttpCodes));
        $codes = array_column($excludedHttpCodes, 'code');
        self::assertContains(404, $codes);
        self::assertContains(405, $codes);
    }
}
