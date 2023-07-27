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

namespace Core\Timezone\Domain\Model;

use Centreon\Domain\Common\Assertion\Assertion;

class Timezone
{
    /**
     * @param int $id
     * @param string $name
     * @param string $offset
     * @param string $dstOffset
     * @param string $description
     * @param string $daylightSavingTimeOffset
     *
     * @throws \Assert\AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly string $offset,
        private readonly string $daylightSavingTimeOffset,
        private readonly string $description = ''
    ) {
        $shortName = (new \ReflectionClass($this))->getShortName();

        Assertion::notEmptyString($name, "{$shortName}::name");
        Assertion::regex($offset, '/^[-+][0-9]{2}:[0-9]{2}$/', "{$shortName}::offset");
        Assertion::regex(
            $daylightSavingTimeOffset,
            '/^[-+][0-9]{2}:[0-9]{2}$/',
            "{$shortName}::daylightSavingTimeOffset"
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOffset(): string
    {
        return $this->offset;
    }

    public function getDaylightSavingTimeOffset(): string
    {
        return $this->daylightSavingTimeOffset;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
