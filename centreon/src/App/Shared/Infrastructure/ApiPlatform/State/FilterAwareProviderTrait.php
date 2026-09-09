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

namespace App\Shared\Infrastructure\ApiPlatform\State;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait FilterAwareProviderTrait
{
    /**
     * Parses a ApiPlatform "<name>[lk]=value" query filter.
     */
    public function handleLikeFilter(mixed $value, string $filterName): ?string
    {
        if ($value === null) {
            return null;
        }

        // a client sending "?<name>=foo" instead of "?<name>[lk]=foo" lands here as a plain string
        if (! is_array($value)) {
            throw new BadRequestHttpException(
                sprintf('The "%s" filter must use the "%s[lk]=value" format.', $filterName, $filterName)
            );
        }

        $likeValue = $value['lk'] ?? null;
        if (is_array($likeValue)) {
            $likeValue = reset($likeValue);
        }

        if (! is_string($likeValue) || $likeValue === '') {
            return null;
        }

        return $likeValue;
    }

    public function handlePositiveIntFilter(mixed $value, string $filterName): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value) || (int) $value <= 0) {
            throw new BadRequestHttpException(sprintf('The "%s" filter must be a positive integer.', $filterName));
        }

        return (int) $value;
    }

    public function handleBoolFilter(mixed $value, string $filterName): ?bool
    {
        if ($value === null) {
            return null;
        }

        $normalized = filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
        if ($normalized === null) {
            throw new BadRequestHttpException(sprintf('The "%s" filter must be a boolean.', $filterName));
        }

        return $normalized;
    }
}
