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

namespace Core\ServiceCategory\Domain\Model;

use Centreon\Domain\Common\Assertion\Assertion;

class NewServiceCategory
{
    public const MAX_NAME_LENGTH = 200,
        MAX_ALIAS_LENGTH = 200;

    protected bool $isActivated = true;

    /**
     * @param string $name
     * @param string $alias
     */
    public function __construct(
        protected string $name,
        protected string $alias
    ) {
        $this->name = trim($name);
        $this->alias = trim($alias);

        $classShortName = (new \ReflectionClass($this))->getShortName();
        Assertion::maxLength($name, self::MAX_NAME_LENGTH, $classShortName . '::name');
        Assertion::notEmpty($name, $classShortName . '::name');
        Assertion::maxLength($alias, self::MAX_ALIAS_LENGTH, $classShortName . '::alias');
        Assertion::notEmpty($alias, $classShortName . '::alias');
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
