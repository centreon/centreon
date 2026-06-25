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
use App\Shared\Infrastructure\Logging\SensitiveKeywordDenylist;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Tests\App\Shared\Infrastructure\Logging\Double\BackedColour;
use Tests\App\Shared\Infrastructure\Logging\Double\BaseWithPrivateField;
use Tests\App\Shared\Infrastructure\Logging\Double\PureColour;
use Tests\App\Shared\Infrastructure\Logging\Double\SensitiveValue;

final class LogPayloadNormalizerTest extends TestCase
{
    private LogPayloadNormalizer $normalizer;

    protected function setUp(): void
    {
        SensitiveKeywordDenylist::reset();
        $this->normalizer = new LogPayloadNormalizer(new CamelCaseToSnakeCaseNameConverter());
    }

    public function testSensitiveKeysAreMaskedAfterSnakeCaseConversion(): void
    {
        // Property names are snake_cased like the framework serializer, so the
        // keyword denylist sees `api_key` / `private_key` and masks them — a
        // camelCase key would slip past those underscore-bearing keywords.
        $message = new class () {
            public string $username = 'admin';

            public string $password = 'secret123';

            public string $apiKey = 'abc';

            public string $accessToken = 'xyz';

            public string $privateKey = 'pem';
        };

        $payload = $this->normalizer->normalize($message);

        self::assertSame('admin', $payload['username']);
        self::assertSame('***', $payload['password']);
        self::assertSame('***', $payload['api_key']);
        self::assertSame('***', $payload['access_token']);
        self::assertSame('***', $payload['private_key']);
    }

    public function testPrivatePropertyWithoutAccessorIsNotLogged(): void
    {
        // The walk exposes the same surface as the standard object normalizer:
        // public, or backed by a public getter. A private getter-less field is
        // internal state — it is not logged at all, so a secret there cannot
        // leak (and never reaches the masking layer).
        $payload = $this->normalizer->normalize(new SensitiveValue());

        self::assertArrayNotHasKey('vault_salt', $payload);
        self::assertSame('visible', $payload['label']);
    }

    public function testGetterBackedPrivatePropertyIsReadByReflectionNotViaTheGetter(): void
    {
        // A private property with a public getter IS exposed (standard surface),
        // but read directly by reflection — the getter is never invoked, which
        // is what keeps the walk safe from a getter returning a fresh instance.
        $message = new class () {
            private string $host = 'real';

            public function getHost(): string
            {
                return $this->host . '-via-getter';
            }
        };

        $payload = $this->normalizer->normalize($message);

        self::assertSame('real', $payload['host']);
    }

    public function testSensitiveArrayKeyIsMasked(): void
    {
        // Masking in the array branch keys off the raw string key (no snake
        // conversion), so an already-underscored sensitive key must be caught.
        $message = new class () {
            /** @var array<string, string> */
            public array $context = ['access_token' => 'xyz', 'name' => 'visible'];
        };

        $payload = $this->normalizer->normalize($message);
        $context = $payload['context'];
        \assert(\is_array($context));

        self::assertSame('***', $context['access_token']);
        self::assertSame('visible', $context['name']);
    }

    public function testLeafObjectsAreRenderedAsReadableScalars(): void
    {
        $message = new class () {
            public BackedColour $backedEnum = BackedColour::Red;

            public PureColour $unitEnum = PureColour::Blue;

            public \DateTimeImmutable $datetime;

            public \Stringable $stringable;

            public int $number = 42;

            public ?string $nothing = null;

            public function __construct()
            {
                $this->datetime = new \DateTimeImmutable('2026-04-30T14:00:00+00:00');
                $this->stringable = new class () implements \Stringable {
                    public function __toString(): string
                    {
                        return 'rendered string';
                    }
                };
            }
        };

        $payload = $this->normalizer->normalize($message);

        self::assertSame('red', $payload['backed_enum']);
        self::assertSame('Blue', $payload['unit_enum']);
        self::assertSame('2026-04-30T14:00:00+00:00', $payload['datetime']);
        self::assertSame('rendered string', $payload['stringable']);
        self::assertSame(42, $payload['number']);
        self::assertNull($payload['nothing']);
    }

    public function testThrowableValueIsRenderedCompactlyNotWalked(): void
    {
        $message = new class () {
            public \Throwable $error;

            public function __construct()
            {
                $this->error = new \RuntimeException('connection refused');
            }
        };

        $payload = $this->normalizer->normalize($message);
        $error = $payload['error'];
        \assert(\is_string($error));

        self::assertStringContainsString('RuntimeException', $error);
        self::assertStringContainsString('connection refused', $error);
    }

    public function testOversizedStringValuesAreTruncated(): void
    {
        // Pin the cap boundary: 1024 chars pass through, 1025 chars are truncated.
        $within = str_repeat('a', 1024);
        $oversize = str_repeat('a', 1025);

        $message = new class ($within, $oversize) {
            public function __construct(
                public string $within,
                public string $oversize,
            ) {
            }
        };

        $payload = $this->normalizer->normalize($message);

        self::assertSame($within, $payload['within']);
        self::assertSame(str_repeat('a', 1024) . '…[truncated]', $payload['oversize']);
    }

    public function testNestedObjectsAreExpandedAndMasked(): void
    {
        $message = new class () {
            public object $child;

            public function __construct()
            {
                $this->child = new class () {
                    public string $name = 'inner';

                    public string $token = 'topsecret';
                };
            }
        };

        $payload = $this->normalizer->normalize($message);

        self::assertSame(['name' => 'inner', 'token' => '***'], $payload['child']);
    }

    public function testPrivatePropertyOfAParentWithAGetterIsWalked(): void
    {
        // A parent's private property has a public getter, so it is exposed;
        // ReflectionObject::getProperties() omits it, hence the hierarchy walk.
        $message = new class () extends BaseWithPrivateField {
            public string $own = 'own';
        };

        $payload = $this->normalizer->normalize($message);

        self::assertSame('base', $payload['base_field']);
        self::assertSame('own', $payload['own']);
    }

    public function testSelfReferentialGraphIsBoundedWithMarker(): void
    {
        // A cycle on the same instance would spin an unguarded normalizer
        // forever; the identity guard cuts it with a marker and terminates.
        $message = new class () {
            public ?object $self = null;

            public string $name = 'root';
        };
        $message->self = $message;

        $payload = $this->normalizer->normalize($message);
        $self = $payload['self'];
        \assert(\is_string($self));

        self::assertSame('root', $payload['name']);
        self::assertStringContainsString('(already logged)', $self);
    }

    public function testSharedReferenceIsRenderedOnce(): void
    {
        // A shared (non-cyclic) reference must not be re-expanded per branch —
        // the global identity set bounds the walk for DAGs, not just cycles.
        $shared = new class () {
            public string $tag = 'shared';
        };
        $message = new class () {
            public ?object $first = null;

            public ?object $second = null;
        };
        $message->first = $shared;
        $message->second = $shared;

        $payload = $this->normalizer->normalize($message);
        $second = $payload['second'];
        \assert(\is_string($second));

        // first is reached first and expanded; second is the same instance,
        // already on the walk, so it collapses to the marker.
        self::assertSame(['tag' => 'shared'], $payload['first']);
        self::assertStringContainsString('(already logged)', $second);
    }

    public function testDeeplyNestedGraphIsBoundedByMaxDepth(): void
    {
        // A chain of distinct instances deeper than MAX_DEPTH (no cycle, so the
        // identity guard never trips) must still terminate via the depth cap.
        $head = null;
        for ($level = 0; $level < 20; $level++) {
            $node = new class () {
                public int $level = 0;

                public ?object $next = null;
            };
            $node->level = $level;
            $node->next = $head;
            $head = $node;
        }

        $payload = $this->normalizer->normalize($head);

        self::assertStringContainsString(
            'max depth',
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
        );
    }

    public function testGlobalNodeBudgetBoundsAWideGraph(): void
    {
        // Per-level caps (depth, items) do not contain a wide-and-deep graph
        // whose node count multiplies (200 x 200 = 40k > the 10k budget); the
        // global node budget does, and the walk stays bounded.
        $message = new class (array_fill(0, 200, array_fill(0, 200, 'x'))) {
            /**
             * @param array<int, array<int, string>> $matrix
             */
            public function __construct(public array $matrix)
            {
            }
        };

        $payload = $this->normalizer->normalize($message);

        self::assertStringContainsString(
            'log truncated',
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
        );
    }

    public function testUnreadablePropertyDegradesToAMarkerWithoutLosingSiblings(): void
    {
        $message = new class () {
            public string $intact = 'fine';

            public \Stringable $boom;

            public function __construct()
            {
                $this->boom = new class () implements \Stringable {
                    public function __toString(): string
                    {
                        throw new \RuntimeException('cannot stringify');
                    }
                };
            }
        };

        $payload = $this->normalizer->normalize($message);
        $boom = $payload['boom'];
        \assert(\is_string($boom));

        self::assertSame('fine', $payload['intact']);
        self::assertStringContainsString('unreadable', $boom);
    }

    public function testCollectionAtExactlyMaxItemsIsNotCapped(): void
    {
        $message = new class (array_fill(0, 1000, 'x')) {
            /**
             * @param list<string> $items
             */
            public function __construct(public array $items)
            {
            }
        };

        $payload = $this->normalizer->normalize($message);
        $items = $payload['items'];
        \assert(\is_array($items));

        self::assertArrayNotHasKey('__truncated__', $items);
        self::assertCount(1000, $items);
    }

    public function testLargeCollectionsAreItemCapped(): void
    {
        $message = new class (array_fill(0, 1001, 'x')) {
            /**
             * @param list<string> $items
             */
            public function __construct(public array $items)
            {
            }
        };

        $payload = $this->normalizer->normalize($message);
        $items = $payload['items'];
        \assert(\is_array($items));

        self::assertArrayHasKey('__truncated__', $items);
        self::assertCount(1001, $items); // 1000 kept + the abort marker
    }
}
