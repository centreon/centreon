<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Common\Domain;

/**
 * This trait is to be used for Event type enums.
 * It provides methods to transform data between enum to legacy string format.
 * Example: 'd,u' <=> [self::Down,self::Unreachable].
 */
trait LegacyEventEnumTrait
{
    /**
     * @param string $legacyStr
     *
     * @throws \Throwable
     *
     * @return self[]
     */
    public static function fromLegacyString(string $legacyStr): array
    {
        $legacyStr = trim($legacyStr);
        if ('' === $legacyStr) {
            return [];
        }
        $legacyValues = explode(',', $legacyStr);
        $legacyValues = array_unique(array_map(trim(...), $legacyValues));
        $events = [];
        foreach ($legacyValues as $value) {
            $events[] = self::from($value);
        }

        return $events;
    }

    /**
     * @param self[] $events
     *
     * @return string
     */
    public static function toLegacyString(array $events): string
    {
        $eventValues = array_map(fn(self $event): string => (string) $event->value, $events);

        return implode(',', array_unique($eventValues));
    }
}
