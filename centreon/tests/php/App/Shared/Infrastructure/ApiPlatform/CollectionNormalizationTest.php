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

use App\Shared\Domain\Collection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Ensures that collections normalized properly by API Platform.
 */
final class CollectionNormalizationTest extends KernelTestCase
{
    public function testNormalizeCollection(): void
    {
        /** @var NormalizerInterface $normalizer */
        $normalizer = self::getContainer()->get(NormalizerInterface::class);

        $items = [(object) ['foo' => 'bar'], (object) ['bar' => 'baz']];
        $collection = new Collection($items, \stdClass::class);
        $lazyCollection = new Collection(fn (): array => $items, \stdClass::class);

        $normalizedItems = $normalizer->normalize($items, 'jsonld');
        $normalizedCollection = $normalizer->normalize($collection, 'jsonld');
        $lazyNormalizedCollection = $normalizer->normalize($lazyCollection, 'jsonld');

        self::assertSame($normalizedItems, $normalizedCollection);
        self::assertSame($normalizedItems, $lazyNormalizedCollection);
    }
}
