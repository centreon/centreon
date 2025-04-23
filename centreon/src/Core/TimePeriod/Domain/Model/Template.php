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

namespace Core\TimePeriod\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

class Template
{
    public const MIN_ALIAS_LENGTH = TimePeriod::MIN_ALIAS_LENGTH;
    public const MAX_ALIAS_LENGTH = TimePeriod::MAX_ALIAS_LENGTH;

    /**
     * @param int $id
     * @param string $alias
     *
     * @throws AssertionFailedException
     */
    public function __construct(readonly private int $id, private string $alias)
    {
        Assertion::min($id, 1, 'Template::id');
        $this->alias = trim($alias);
        Assertion::minLength(
            $this->alias,
            self::MIN_ALIAS_LENGTH,
            'Template::alias'
        );
        Assertion::maxLength(
            $this->alias,
            self::MAX_ALIAS_LENGTH,
            'Template::alias'
        );
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias;
    }
}
