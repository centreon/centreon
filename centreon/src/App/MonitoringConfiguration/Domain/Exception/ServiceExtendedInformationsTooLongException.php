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

namespace App\MonitoringConfiguration\Domain\Exception;

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use App\Shared\Domain\Exception\AggregateConflictException;

/**
 * A note, note URL or action URL longer than the form allows is written. Such a text stored by legacy is still
 * read and returned (the value object only bounds it by its column), but a write must bring it within the form
 * bounds. Reported against `monitoringEngine`, the field of the input these texts are given in.
 */
final class ServiceExtendedInformationsTooLongException extends AggregateConflictException
{
    /**
     * @param list<string> $fields the fields above their form bound
     */
    public function __construct(array $fields)
    {
        parent::__construct(
            ['monitoringEngine' => $fields],
            sprintf(
                'The note cannot exceed %d characters, the note URL and the action URL %d characters.',
                ServiceExtendedInformations::MAX_NOTE_LENGTH,
                ServiceExtendedInformations::MAX_NOTE_URL_LENGTH,
            ),
        );
    }
}
