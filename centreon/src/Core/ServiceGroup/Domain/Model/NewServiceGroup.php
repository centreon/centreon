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

class NewServiceGroup
{
    public const MAX_NAME_LENGTH = 200;
    public const MIN_NAME_LENGTH = 1;
    public const MAX_ALIAS_LENGTH = 200;
    public const MIN_ALIAS_LENGTH = 1;
    public const MAX_COMMENT_LENGTH = 65535;

    /**
     * @param string $name
     * @param string $alias
     * @param GeoCoords|null $geoCoords
     * @param string $comment
     * @param bool $isActivated
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        protected string $name,
        protected string $alias,
        protected null|GeoCoords $geoCoords = null,
        protected string $comment = '',
        protected bool $isActivated = true,
    ) {
        $shortName = (new \ReflectionClass($this))->getShortName();

        $this->name = trim($this->name);
        Assertion::minLength($this->name, self::MIN_NAME_LENGTH, "{$shortName}::name");
        Assertion::maxLength($this->name, self::MAX_NAME_LENGTH, "{$shortName}::name");

        $this->alias = trim($this->alias);
        Assertion::maxLength($this->alias, self::MAX_ALIAS_LENGTH, "{$shortName}::alias");
        Assertion::minLength($this->name, self::MIN_ALIAS_LENGTH, "{$shortName}::name");

        $this->comment = trim($this->comment);
        Assertion::maxLength($this->comment, self::MAX_COMMENT_LENGTH, "{$shortName}::comment");
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function isActivated(): bool
    {
        return $this->isActivated;
    }

    public function getGeoCoords(): ?GeoCoords
    {
        return $this->geoCoords;
    }
}
