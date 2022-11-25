<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\HostCategory\Domain\Model;

use Centreon\Domain\Common\Assertion\Assertion;

class NewHostCategory
{
    public const MAX_NAME_LENGTH = 255,
                MAX_ALIAS_LENGTH = 255;

    /** @var Host[] */
    protected array $hosts = [];

    /** @var HostTemplate[] */
    protected array $hostTemplates = [];

    public function __construct(
        protected string $name,
        protected string $alias
    ) {
        Assertion::maxLength($name, self::MAX_NAME_LENGTH, 'HostCategory::name');
        Assertion::notEmpty($name, 'HostCategory::name');
        Assertion::maxLength($alias, self::MAX_ALIAS_LENGTH, 'HostCategory::alias');
        Assertion::notEmpty($alias, 'HostCategory::alias');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }
}
