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

use Centreon\Domain\Common\Assertion\Assertion;
use Core\Contact\Domain\Model\ContactTemplate;

/**
 * This class represent a User being created.
 */
class NewUser
{
    public const MIN_ALIAS_LENGTH = 1,
                 MAX_ALIAS_LENGTH = 255,
                 MIN_NAME_LENGTH = 1,
                 MAX_NAME_LENGTH = 255,
                 MIN_EMAIL_LENGTH = 1,
                 MAX_EMAIL_LENGTH = 255,
                 MIN_THEME_LENGTH = 1,
                 MAX_THEME_LENGTH = 100,
                 MAX_USER_INTERFACE_DENSITY_LENGTH = 100,
                 THEME_LIGHT = 'light',
                 THEME_DARK = 'dark',
                 USER_INTERFACE_DENSITY_EXTENDED = 'extended',
                 USER_INTERFACE_DENSITY_COMPACT = 'compact';

    /** @var bool */
    protected bool $isActivate = true;

    /** @var bool */
    protected bool $isAdmin = false;

    /** @var string */
    protected string $theme = self::THEME_LIGHT;

    /** @var ContactTemplate|null */
    protected ?ContactTemplate $contactTemplate = null;

    protected string $userInterfaceDensity = self::USER_INTERFACE_DENSITY_COMPACT;

    protected bool $canReachFrontend = true;

    protected bool $canReachRealtimeApi = false;

    protected bool $canReachConfigurationApi = false;

    /**
     * @param string $alias
     * @param string $name
     * @param string $email
     *
     * @throws \Assert\AssertionFailedException
     */
    public function __construct(
        protected string $alias,
        protected string $name,
        protected string $email,
    ) {
        $this->setAlias($alias);
        $this->setName($name);
        $this->setEmail($email);
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
        Assertion::notEmptyString(
            $userInterfaceDensity,
            'User::userInterfaceViewMode'
        );

        Assertion::maxLength(
            $userInterfaceDensity,
            self::MAX_USER_INTERFACE_DENSITY_LENGTH,
            'User::userInterfaceViewMode'
        );

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

    /**
     * @return bool
     */
    public function canReachRealtimeApi(): bool
    {
        return $this->canReachRealtimeApi;
    }

    /**
     * @param bool $canReachRealtimeApi
     *
     * @return self
     */
    public function setCanReachRealtimeApi(bool $canReachRealtimeApi): self
    {
        $this->canReachRealtimeApi = $canReachRealtimeApi;

        return $this;
    }

    /**
     * @param bool $canReachConfigurationApi
     *
     * @return self
     */
    public function setCanReachConfigurationApi(bool $canReachConfigurationApi): self
    {
        $this->canReachConfigurationApi = $canReachConfigurationApi;

        return $this;
    }

    /**
     * @return bool
     */
    public function canReachConfigurationApi(): bool
    {
        return $this->canReachConfigurationApi;
    }
}
