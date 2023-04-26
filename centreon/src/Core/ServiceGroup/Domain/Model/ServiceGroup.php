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

namespace Core\ServiceGroup\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Core\Domain\Common\GeoCoords;

class ServiceGroup extends NewServiceGroup
{
    /** @var positive-int */
    private int $id;

    /**
     * @param positive-int $id
     * @param string $name
     * @param string $alias
     * @param GeoCoords|null $geoCoords
     * @param string $comment
     * @param bool $isActivated
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        int $id,
        string $name,
        string $alias,
        null|GeoCoords $geoCoords,
        string $comment,
        bool $isActivated,
    ) {
        Assertion::positiveInt($id, 'ServiceGroup::id');
        $this->id = $id;

        parent::__construct(
            $name,
            $alias,
            $geoCoords,
            $comment,
            $isActivated,
        );
    }

    /**
     * @return positive-int
     */
    public function getId(): int
    {
        return $this->id;
    }
}
