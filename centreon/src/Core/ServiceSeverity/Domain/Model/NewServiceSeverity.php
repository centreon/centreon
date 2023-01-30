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

namespace Core\ServiceSeverity\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

class NewServiceSeverity
{
    public const MAX_NAME_LENGTH = 200,
        MAX_ALIAS_LENGTH = 200,
        MIN_LEVEL_VALUE = -128,
        MAX_LEVEL_VALUE = 127;

    protected bool $isActivated = true;

    protected ?string $comment = null;

    /**
     * @param string $name
     * @param string $alias
     * @param int $level
     * @param positive-int $iconId FK
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        protected string $name,
        protected string $alias,
        protected int $level,
        protected int $iconId
    ) {
        $this->name = trim($name);
        $this->alias = trim($alias);

        $shortName = (new \ReflectionClass($this))->getShortName();
        Assertion::maxLength($name, self::MAX_NAME_LENGTH, $shortName . '::name');
        Assertion::notEmpty($name, $shortName . '::name');
        Assertion::maxLength($alias, self::MAX_ALIAS_LENGTH, $shortName . '::alias');
        Assertion::notEmpty($alias, $shortName . '::alias');
        Assertion::min($level, self::MIN_LEVEL_VALUE, $shortName . '::level');
        Assertion::max($level, self::MAX_LEVEL_VALUE, $shortName . '::level');
        Assertion::positiveInt($iconId, "{$shortName}::iconId");
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * @return int
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * @return int
     */
    public function getIconId(): int
    {
        return $this->iconId;
    }

    /**
     * @return bool
     */
    public function isActivated(): bool
    {
        return $this->isActivated;
    }

    /**
     * @param bool $isActivated
     */
    public function setActivated(bool $isActivated): void
    {
        $this->isActivated = $isActivated;
    }
}
