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

use App\Shared\Infrastructure\Logging\LogPayloadNormalizer;
use App\Shared\Infrastructure\Logging\NonArrayNormalizationException;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Tests\App\Shared\Infrastructure\Logging\Double\BackedColour;
use Tests\App\Shared\Infrastructure\Logging\Double\HiddenSecret;
use Tests\App\Shared\Infrastructure\Logging\Double\PureColour;

final class LogPayloadNormalizerTest extends TestCase
{
    private NormalizerInterface&Stub $inner;

    protected function setUp(): void
    {
        $this->inner = $this->createStub(NormalizerInterface::class);
    }

    public function testSensitiveFieldsAreMasked(): void
    {
        $this->inner->method('normalize')->willReturn([
            'username' => 'admin',
            'password' => 'secret123',
            'api_key' => 'abc',
            'credentials_token' => 'xyz',
            'private_key' => '-----BEGIN RSA PRIVATE KEY-----',
            'ssh_private_key_pem' => 'pem-content',
        ]);

        $payload = (new LogPayloadNormalizer($this->inner))->normalize(new \stdClass());

        self::assertSame('admin', $payload['username']);
        self::assertSame('***', $payload['password']);
        self::assertSame('***', $payload['api_key']);
        self::assertSame('***', $payload['credentials_token']);
        self::assertSame('***', $payload['private_key']);
        self::assertSame('***', $payload['ssh_private_key_pem']);
    }

    public function testNormalizedObjectsAreSanitisedDefensively(): void
    {
        // If a value remains an object after normalisation, each supported
        // flavour is rendered defensively so private state never leaks.
        $this->inner->method('normalize')->willReturn([
            'enum_backed' => BackedColour::Red,
            'enum_unit' => PureColour::Blue,
            'datetime' => new \DateTimeImmutable('2026-04-30T14:00:00+00:00'),
            'stringable' => new class () implements \Stringable {
                public function __toString(): string
                {
                    return 'rendered string';
                }
            },
            'plain_object' => new HiddenSecret('should-not-appear'),
        ]);

        $payload = (new LogPayloadNormalizer($this->inner))->normalize(new \stdClass());

        self::assertSame('red', $payload['enum_backed']);
        self::assertSame('Blue', $payload['enum_unit']);
        self::assertSame('2026-04-30T14:00:00+00:00', $payload['datetime']);
        self::assertSame('rendered string', $payload['stringable']);
        self::assertSame('{' . HiddenSecret::class . '}', $payload['plain_object']);
    }

    public function testStringLongerThanMaxValueLengthIsTruncated(): void
    {
        // Pin the cap boundary: 1024 chars passes through, 1025 chars is truncated.
        $within = str_repeat('a', 1024);
        $oversize = str_repeat('a', 1025);

        $this->inner->method('normalize')->willReturn([
            'within' => $within,
            'oversize' => $oversize,
        ]);

        $payload = (new LogPayloadNormalizer($this->inner))->normalize(new \stdClass());

        self::assertSame($within, $payload['within']);
        self::assertSame(str_repeat('a', 1024) . '…[truncated]', $payload['oversize']);
    }

    public function testThrowsNonArrayNormalizationExceptionWhenInnerReturnsScalar(): void
    {
        // A non-array return from the inner normalizer must surface as an
        // explicit exception, not silently corrupt the sanitisation pass.
        $this->inner->method('normalize')->willReturn('unexpected scalar payload');

        $normalizer = new LogPayloadNormalizer($this->inner);

        try {
            $normalizer->normalize(new \stdClass());
            self::fail('NonArrayNormalizationException was not thrown.');
        } catch (NonArrayNormalizationException $e) {
            self::assertSame(\stdClass::class, $e->messageClass);
            self::assertSame('string', $e->returnedType);
        }
    }
}
