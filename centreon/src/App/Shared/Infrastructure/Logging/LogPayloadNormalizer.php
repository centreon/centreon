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

namespace App\Shared\Infrastructure\Logging;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Turns a bus message into a redacted, log-safe array payload without
 * delegating to the unbounded Symfony serializer, whose object normalizer
 * recurses with no depth guard and would freeze (CPU-bound, no exception) on a
 * cyclic, shared-reference or very deep object graph.
 *
 * The walk is bounded the way Monolog's NormalizerFormatter bounds arbitrary
 * log data — recursion depth, per-collection item count and value length —
 * plus a global node budget (so a wide-and-deep graph, which per-level caps
 * alone do not contain, cannot exhaust CPU/memory) and object-identity
 * tracking (a cycle or a repeated reference renders once).
 *
 * It exposes the same surface as the standard object normalizer — public
 * properties and properties backed by a public getter (get/is/has/can) — but
 * reads them by reflection and never invokes a getter, so a getter that
 * returns a fresh instance of its own type cannot drive the walk. Internal
 * state with no public accessor stays out of the log entirely. Keyword-matched
 * keys are masked here; attribute-driven `#[Sensitive]` masking is applied
 * downstream by PayloadSanitizer.
 */
final readonly class LogPayloadNormalizer
{
    private const MAX_DEPTH = 9;
    private const MAX_ITEMS = 1000;
    private const MAX_NODES = 10000;
    private const MAX_VALUE_LENGTH = 1024;
    private const TRUNCATED_KEY = '__truncated__';

    /** @var list<string> */
    private const ACCESSOR_PREFIXES = ['get', 'is', 'has', 'can'];

    public function __construct(
        #[Autowire(service: 'serializer.name_converter.camel_case_to_snake_case')]
        private NameConverterInterface $nameConverter,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(object $message): array
    {
        $seen = [spl_object_id($message) => true];
        $budget = self::MAX_NODES;

        return $this->normalizeObjectProperties($message, 0, $seen, $budget);
    }

    /**
     * @param array<int, true> $seen object ids already rendered on this walk
     * @param int $budget remaining nodes the whole walk may still render
     */
    private function normalizeValue(mixed $value, int $depth, array &$seen, int &$budget): mixed
    {
        if ($budget-- <= 0) {
            return sprintf('[log truncated: over %d nodes]', self::MAX_NODES);
        }

        if ($depth > self::MAX_DEPTH) {
            return sprintf('[max depth %d reached]', self::MAX_DEPTH);
        }

        if (\is_array($value)) {
            return $this->normalizeArray($value, $depth, $seen, $budget);
        }

        if (\is_object($value)) {
            return $this->normalizeObject($value, $depth, $seen, $budget);
        }

        if (\is_string($value)) {
            return $this->capLength($value);
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<int, true> $seen
     *
     * @return array<array-key, mixed>
     */
    private function normalizeArray(array $data, int $depth, array &$seen, int &$budget): array
    {
        $result = [];
        $count = 0;
        foreach ($data as $key => $value) {
            if ($count++ >= self::MAX_ITEMS) {
                $result[self::TRUNCATED_KEY] = sprintf('over %d items, aborting', self::MAX_ITEMS);

                break;
            }

            if (\is_string($key) && SensitiveKeywordDenylist::matches($key)) {
                $result[$key] = '***';

                continue;
            }

            try {
                $result[$key] = $this->normalizeValue($value, $depth + 1, $seen, $budget);
            } catch (\Throwable $e) {
                $result[$key] = sprintf('{unreadable %s: %s}', $key, get_debug_type($e));
            }
        }

        return $result;
    }

    /**
     * @param array<int, true> $seen
     */
    private function normalizeObject(object $data, int $depth, array &$seen, int &$budget): mixed
    {
        if ($data instanceof \BackedEnum) {
            return $data->value;
        }

        if ($data instanceof \UnitEnum) {
            return $data->name;
        }

        if ($data instanceof \DateTimeInterface) {
            return $data->format(\DateTimeInterface::ATOM);
        }

        // Before Stringable: \Exception is Stringable, and (string) would dump the whole trace.
        if ($data instanceof \Throwable) {
            return '{' . $data::class . ': ' . $this->capLength($data->getMessage()) . '}';
        }

        if ($data instanceof \Stringable) {
            return $this->capLength((string) $data);
        }

        $objectId = spl_object_id($data);
        if (isset($seen[$objectId])) {
            return '{' . $data::class . ' (already logged)}';
        }
        $seen[$objectId] = true;

        return $this->normalizeObjectProperties($data, $depth, $seen, $budget);
    }

    /**
     * @param array<int, true> $seen
     *
     * @return array<string, mixed>
     */
    private function normalizeObjectProperties(object $object, int $depth, array &$seen, int &$budget): array
    {
        $result = [];
        $count = 0;
        foreach ($this->loggableProperties($object) as $property) {
            if (! $property->isInitialized($object)) {
                continue;
            }

            if ($count++ >= self::MAX_ITEMS) {
                $result[self::TRUNCATED_KEY] = sprintf('over %d items, aborting', self::MAX_ITEMS);

                break;
            }

            $key = $this->nameConverter->normalize($property->getName());

            if (SensitiveKeywordDenylist::matches($key)) {
                $result[$key] = '***';

                continue;
            }

            try {
                $result[$key] = $this->normalizeValue($property->getValue($object), $depth + 1, $seen, $budget);
            } catch (\Throwable $e) {
                $result[$key] = sprintf('{unreadable %s: %s}', $key, get_debug_type($e));
            }
        }

        return $result;
    }

    /**
     * Instance properties — own and inherited — that the standard object
     * normalizer would expose: public, or backed by a public getter
     * (get/is/has/can). The getter is never invoked; the backing property is
     * read by reflection, which keeps the walk immune to a getter that returns
     * a fresh instance of its own type. A property with no public accessor is
     * internal state and stays out of the log. The hierarchy is walked because
     * `ReflectionObject::getProperties()` omits a parent's private properties.
     *
     * @return list<\ReflectionProperty>
     */
    private function loggableProperties(object $object): array
    {
        $properties = [];
        $class = new \ReflectionObject($object);
        while ($class !== false) {
            foreach ($class->getProperties() as $property) {
                if ($property->isStatic()) {
                    continue;
                }
                if (isset($properties[$property->getName()])) {
                    continue;
                }
                if ($property->isPublic() || $this->hasPublicAccessor($object, $property->getName())) {
                    $properties[$property->getName()] = $property;
                }
            }
            $class = $class->getParentClass();
        }

        return array_values($properties);
    }

    private function hasPublicAccessor(object $object, string $property): bool
    {
        $suffix = ucfirst($property);
        foreach (self::ACCESSOR_PREFIXES as $prefix) {
            $method = $prefix . $suffix;
            if (method_exists($object, $method) && (new \ReflectionMethod($object, $method))->isPublic()) {
                return true;
            }
        }

        return false;
    }

    private function capLength(string $value): string
    {
        return \mb_strlen($value) > self::MAX_VALUE_LENGTH
            ? mb_substr($value, 0, self::MAX_VALUE_LENGTH) . '…[truncated]'
            : $value;
    }
}
