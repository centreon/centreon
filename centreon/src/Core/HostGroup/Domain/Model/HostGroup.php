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

namespace Core\HostGroup\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Core\Domain\Common\GeoCoords;

class HostGroup extends NewHostGroup
{
    /** @var positive-int */
    private int $id;

    /**
     * @param positive-int $id
     * @param string $name
     * @param string $alias
     * @param string $notes
     * @param string $notesUrl
     * @param string $actionUrl
     * @param positive-int|null $iconId FK
     * @param positive-int|null $iconMapId FK
     * @param int|null $rrdRetention Days
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
        string $notes,
        string $notesUrl,
        string $actionUrl,
        ?int $iconId,
        ?int $iconMapId,
        ?int $rrdRetention,
        null|GeoCoords $geoCoords,
        string $comment,
        bool $isActivated,
    ) {
        Assertion::positiveInt($id, 'HostGroup::id');
        $this->id = $id;

        parent::__construct(
            $name,
            $alias,
            $notes,
            $notesUrl,
            $actionUrl,
            $iconId,
            $iconMapId,
            $rrdRetention,
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
