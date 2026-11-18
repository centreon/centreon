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

namespace App\Upgrade\Domain\Exception;

/**
 * Closing steps of the platform update failed, after all of them ran.
 */
final class ClosingStepsFailedException extends \RuntimeException
{
    /**
     * @param non-empty-array<string, \Throwable> $failures the failure of each failed step, by step name
     */
    public function __construct(array $failures)
    {
        $messages = [];
        foreach ($failures as $step => $failure) {
            $messages[] = sprintf('%s: %s', $step, rtrim($failure->getMessage(), '.'));
        }

        parent::__construct(sprintf('The closing steps of the update failed. %s.', implode('; ', $messages)), 0, reset($failures));
    }
}
