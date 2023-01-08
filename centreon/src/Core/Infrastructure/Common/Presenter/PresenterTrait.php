<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Core\Infrastructure\Common\Presenter;

/**
 * This trait is here only to expose utility methods **only** to avoid duplicate code.
 * The methods SHOULD be "Pure" functions.
 */
trait PresenterTrait
{
    /**
     * Convert a DateTime into a string format ISO 8601.
     *
     * @param \DateTimeInterface|null $date
     *
     * @return string|null
     */
    public function formatDateToIso8601(?\DateTimeInterface $date): ?string
    {
        return $date !== null ? $date->format(\DateTime::ISO8601) : $date;
    }

    /**
     * Transform an empty string `''` in `null` value, otherwise keep the same string.
     *
     * @phpstan-pure
     *
     * @param string $string
     *
     * @return string|null
     */
    public function emptyStringAsNull(string $string): ?string
    {
        return '' === $string ? null : $string;
    }
}
