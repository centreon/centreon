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

namespace CentreonUser\Application\Serializer\Timeperiod;

use Centreon\Application\Serializer\SerializerContextInterface;
use CentreonUser\Domain\Entity\Timeperiod;

/**
 * @OA\Schema(
 *   schema="TimeperiodEntity",
 *
 *       @OA\Property(property="id", type="integer"),
 *       @OA\Property(property="name", type="string"),
 *       @OA\Property(property="alias", type="string")
 * )
 *
 * Serialize Timeperiod entity for list
 */
class ListContext implements SerializerContextInterface
{
    /**
     * {@inheritDoc}
     */
    public static function context(): array
    {
        return [
            static::GROUPS => [
                Timeperiod::SERIALIZER_GROUP_LIST,
            ],
        ];
    }
}
