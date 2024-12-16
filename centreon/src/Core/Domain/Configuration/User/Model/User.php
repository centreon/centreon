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

namespace Core\Domain\Configuration\User\Model;

class User extends NewUser
{
    public const MIN_ALIAS_LENGTH = 1;
    public const MAX_ALIAS_LENGTH = 255;
    public const MIN_NAME_LENGTH = 1;
    public const MAX_NAME_LENGTH = 255;
    public const MIN_EMAIL_LENGTH = 1;
    public const MAX_EMAIL_LENGTH = 255;
    public const MIN_THEME_LENGTH = 1;
    public const MAX_THEME_LENGTH = 100;
    public const THEME_LIGHT = 'light';
    public const THEME_DARK = 'dark';
    public const USER_INTERFACE_DENSITY_EXTENDED = 'extended';
    public const USER_INTERFACE_DENSITY_COMPACT = 'compact';

    /**
     * @param int $id
     * @param string $alias
     * @param string $name
     * @param string $email
     * @param bool $isAdmin
     * @param string $theme
     * @param string $userInterfaceDensity
     * @param bool $canReachFrontend
     *
     * @throws \Assert\AssertionFailedException
     */
    public function __construct(
        private int $id,
        protected string $alias,
        protected string $name,
        protected string $email,
        protected bool $isAdmin,
        protected string $theme,
        protected string $userInterfaceDensity,
        protected bool $canReachFrontend
    ) {
        parent::__construct($alias, $name, $email);
        $this->setTheme($theme);
        $this->setUserInterfaceDensity($userInterfaceDensity);
        $this->setCanReachFrontend($canReachFrontend);
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }
}
