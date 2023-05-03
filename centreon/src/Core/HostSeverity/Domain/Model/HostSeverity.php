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

namespace Core\HostSeverity\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

class HostSeverity extends NewHostSeverity
{
    /**
     * @param int $id
     * @param string $name
     * @param string $alias
     * @param int $level
     * @param positive-int $iconId
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly int $id,
        string $name,
        string $alias,
        int $level,
        int $iconId
    ) {
        parent::__construct($name, $alias, $level, $iconId);
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param string $name
     *
     * @throws AssertionFailedException
     */
    public function setName(string $name): void
    {
        $name = trim($name);
        $shortName = (new \ReflectionClass($this))->getShortName();
        Assertion::maxLength($name, self::MAX_NAME_LENGTH, $shortName . '::name');
        Assertion::notEmptyString($name, $shortName . '::name');

        $this->name = $name;
    }

    /**
     * @param string $alias
     *
     * @throws AssertionFailedException
     */
    public function setAlias(string $alias): void
    {
        $alias = trim($alias);
        $shortName = (new \ReflectionClass($this))->getShortName();
        Assertion::maxLength($alias, self::MAX_NAME_LENGTH, $shortName . '::alias');
        Assertion::notEmptyString($alias, $shortName . '::alias');

        $this->alias = $alias;
    }

    /**
     * @param int $iconId
     *
     * @throws AssertionFailedException
     */
    public function setIconId(int $iconId): void
    {
        $shortName = (new \ReflectionClass($this))->getShortName();
        Assertion::positiveInt($iconId, "{$shortName}::iconId");

        $this->iconId = $iconId;
    }

    /**
     * @param int $level
     *
     * @throws AssertionFailedException
     */
    public function setLevel(int $level): void
    {
        $shortName = (new \ReflectionClass($this))->getShortName();
        Assertion::min($level, self::MIN_LEVEL_VALUE, $shortName . '::level');
        Assertion::max($level, self::MAX_LEVEL_VALUE, $shortName . '::level');

        $this->level = $level;
    }
}
