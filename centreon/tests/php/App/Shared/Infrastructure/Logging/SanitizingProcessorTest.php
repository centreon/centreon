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

use App\Shared\Infrastructure\Logging\PayloadSanitizer;
use App\Shared\Infrastructure\Logging\SanitizingProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class SanitizingProcessorTest extends TestCase
{
    private SanitizingProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new SanitizingProcessor(new PayloadSanitizer());
    }

    public function testMasksKeywordKeysOfAdHocContextWhenNoClassContextIsAvailable(): void
    {
        // The cross-channel safety net catches ad-hoc raw arrays too: an
        // `$logger->error('msg', ['password' => $raw])` carries no class
        // context, so there is no `#[Sensitive]` to reflect — the shared
        // keyword denylist masks the matching keys instead. Keys that
        // match nothing (`login`) stay in clear.
        $record = $this->makeRecord(['login' => 'admin', 'password' => 'secret123']);

        $processed = ($this->processor)($record);

        self::assertSame(['login' => 'admin', 'password' => '***'], $processed->context);
    }

    public function testKeepsThrowableInstancesUntouchedForDownstreamFormatter(): void
    {
        // ExceptionFormatterProcessor relies on `context.exception`
        // being a real Throwable — the sanitiser must not turn it into
        // a placeholder string. Pin that contract here.
        $exception = new \RuntimeException('boom');
        $record = $this->makeRecord(['exception' => $exception, 'context_id' => 7]);

        $processed = ($this->processor)($record);

        self::assertSame($exception, $processed->context['exception']);
        self::assertSame(7, $processed->context['context_id']);
    }

    public function testPreservesNonContextRecordFields(): void
    {
        $record = $this->makeRecord(['note' => 'a payload']);

        $processed = ($this->processor)($record);

        self::assertSame($record->channel, $processed->channel);
        self::assertSame($record->message, $processed->message);
        self::assertSame($record->level, $processed->level);
    }

    public function testMasksUrlQuerySecretsInExtraWhileKeepingAuditFields(): void
    {
        // WebProcessor records the request URI in `extra.url`; a secret passed
        // as a query parameter must be redacted. The other `extra` fields set
        // by platform processors (e.g. `token` => TokenProcessor's audit
        // descriptor of the authenticated user, not a credential) are not user
        // input and stay readable for auditing.
        $record = $this->makeRecord(
            context: [],
            extra: [
                'url' => '/centreon/api/latest/login?useralias=admin&token=leaked',
                'token' => ['authenticated' => true, 'user_identifier' => 'admin'],
                'ip' => '203.0.113.7',
            ],
        );

        $processed = ($this->processor)($record);

        self::assertSame(
            '/centreon/api/latest/login?useralias=admin&token=***',
            $processed->extra['url'],
        );
        self::assertSame(['authenticated' => true, 'user_identifier' => 'admin'], $processed->extra['token']);
        self::assertSame('203.0.113.7', $processed->extra['ip']);
    }

    public function testMasksKeywordKeysInContextButNotInExtraSimultaneously(): void
    {
        $record = $this->makeRecord(
            context: ['token' => 'secret-credential', 'user' => 'admin'],
            extra: ['token' => ['authenticated' => true, 'user_identifier' => 'admin'], 'password' => 'audit-hash'],
        );

        $processed = ($this->processor)($record);

        self::assertSame('***', $processed->context['token']);
        self::assertSame('admin', $processed->context['user']);
        self::assertSame(['authenticated' => true, 'user_identifier' => 'admin'], $processed->extra['token']);
        self::assertSame('audit-hash', $processed->extra['password']);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     */
    private function makeRecord(array $context, array $extra = []): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Error,
            message: 'a message',
            context: $context,
            extra: $extra,
        );
    }
}
