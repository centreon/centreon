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

namespace Core\User\Domain\Model;

use Centreon\Domain\Common\Assertion\Assertion;
use Core\Contact\Domain\Model\ContactTemplate;

class User
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

    /** @var bool */
    protected bool $isActivate = true;

    /** @var ContactTemplate|null */
    protected ?ContactTemplate $contactTemplate = null;

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
        Assertion::positiveInt($this->id, 'User::id');

        $this->setAlias($alias);
        $this->setName($name);
        $this->setEmail($email);
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

    /**
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * @param string $alias
     *
     * @throws \Assert\AssertionFailedException
     *
     * @return self
     */
    public function setAlias(string $alias): self
    {
        Assertion::minLength($alias, self::MIN_ALIAS_LENGTH, 'User::alias');
        Assertion::maxLength($alias, self::MAX_ALIAS_LENGTH, 'User::alias');
        $this->alias = $alias;

        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @throws \Assert\AssertionFailedException
     *
     * @return self
     */
    public function setName(string $name): self
    {
        Assertion::minLength($name, self::MIN_ALIAS_LENGTH, 'User::name');
        Assertion::maxLength($name, self::MAX_ALIAS_LENGTH, 'User::name');
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param string $email
     *
     * @throws \Assert\AssertionFailedException
     *
     * @return self
     */
    public function setEmail(string $email): self
    {
        // Email format validation cannot be done here until legacy form does not check it
        Assertion::minLength($email, self::MIN_EMAIL_LENGTH, 'User::email');
        Assertion::maxLength($email, self::MAX_EMAIL_LENGTH, 'User::email');
        $this->email = $email;

        return $this;
    }

    /**
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    /**
     * @param bool $isAdmin
     *
     * @return self
     */
    public function setAdmin(bool $isAdmin): self
    {
        $this->isAdmin = $isAdmin;

        return $this;
    }

    /**
     * @return string
     */
    public function getTheme(): string
    {
        return $this->theme;
    }

    /**
     * @param string $theme
     *
     * @throws \Assert\AssertionFailedException
     *
     * @return self
     */
    public function setTheme(string $theme): self
    {
        Assertion::minLength($theme, self::MIN_THEME_LENGTH, 'User::theme');
        Assertion::maxLength($theme, self::MAX_THEME_LENGTH, 'User::theme');
        $this->theme = $theme;

        return $this;
    }

    /**
     * @return ContactTemplate|null
     */
    public function getContactTemplate(): ?ContactTemplate
    {
        return $this->contactTemplate;
    }

    /**
     * @param ContactTemplate|null $contactTemplate
     *
     * @return self
     */
    public function setContactTemplate(?ContactTemplate $contactTemplate): self
    {
        $this->contactTemplate = $contactTemplate;

        return $this;
    }

    /**
     * @return bool
     */
    public function isActivate(): bool
    {
        return $this->isActivate;
    }

    /**
     * @param bool $isActivate
     *
     * @return self
     */
    public function setActivate(bool $isActivate): self
    {
        $this->isActivate = $isActivate;

        return $this;
    }

    /**
     * @return string
     */
    public function getUserInterfaceDensity(): string
    {
        return $this->userInterfaceDensity;
    }

    /**
     * @param string $userInterfaceDensity
     *
     * @throws \Assert\AssertionFailedException
     * @throws \InvalidArgumentException
     *
     * @return self
     */
    public function setUserInterfaceDensity(string $userInterfaceDensity): self
    {
        if (
            $userInterfaceDensity !== self::USER_INTERFACE_DENSITY_EXTENDED
            && $userInterfaceDensity !== self::USER_INTERFACE_DENSITY_COMPACT
        ) {
            throw new \InvalidArgumentException('User interface view mode provided not handled');
        }

        $this->userInterfaceDensity = $userInterfaceDensity;

        return $this;
    }

    /**
     * @return bool
     */
    public function canReachFrontend(): bool
    {
        return $this->canReachFrontend;
    }

    /**
     * @param bool $canReachFrontend
     *
     * @return self
     */
    public function setCanReachFrontend(bool $canReachFrontend): self
    {
        $this->canReachFrontend = $canReachFrontend;

        return $this;
    }
}
