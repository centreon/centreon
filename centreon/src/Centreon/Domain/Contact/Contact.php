<?php

/*
 * Copyright 2005 - 2020 Centreon (https://www.centreon.com/)
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

namespace Centreon\Domain\Contact;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Menu\Model\Page;
use Symfony\Component\Security\Core\User\UserInterface;

class Contact implements UserInterface, ContactInterface
{
    // global api roles
    public const ROLE_API_CONFIGURATION = 'ROLE_API_CONFIGURATION';
    public const ROLE_API_REALTIME = 'ROLE_API_REALTIME';

    // user action roles
    public const ROLE_HOST_CHECK = 'ROLE_HOST_CHECK';
    public const ROLE_HOST_FORCED_CHECK = 'ROLE_HOST_FORCED_CHECK';
    public const ROLE_SERVICE_CHECK = 'ROLE_SERVICE_CHECK';
    public const ROLE_SERVICE_FORCED_CHECK = 'ROLE_SERVICE_FORCED_CHECK';
    public const ROLE_HOST_ACKNOWLEDGEMENT = 'ROLE_HOST_ACKNOWLEDGEMENT';
    public const ROLE_HOST_DISACKNOWLEDGEMENT = 'ROLE_HOST_DISACKNOWLEDGEMENT';
    public const ROLE_SERVICE_ACKNOWLEDGEMENT = 'ROLE_SERVICE_ACKNOWLEDGEMENT';
    public const ROLE_SERVICE_DISACKNOWLEDGEMENT = 'ROLE_SERVICE_DISACKNOWLEDGEMENT';
    public const ROLE_CANCEL_HOST_DOWNTIME = 'ROLE_CANCEL_HOST_DOWNTIME';
    public const ROLE_CANCEL_SERVICE_DOWNTIME = 'ROLE_CANCEL_SERVICE_DOWNTIME';
    public const ROLE_ADD_HOST_DOWNTIME = 'ROLE_ADD_HOST_DOWNTIME';
    public const ROLE_ADD_SERVICE_DOWNTIME = 'ROLE_ADD_SERVICE_DOWNTIME';
    public const ROLE_SERVICE_SUBMIT_RESULT = 'ROLE_SERVICE_SUBMIT_RESULT';
    public const ROLE_HOST_SUBMIT_RESULT = 'ROLE_HOST_SUBMIT_RESULT';
    public const ROLE_HOST_ADD_COMMENT = 'ROLE_HOST_ADD_COMMENT';
    public const ROLE_SERVICE_ADD_COMMENT = 'ROLE_SERVICE_ADD_COMMENT';
    public const ROLE_DISPLAY_COMMAND = 'ROLE_DISPLAY_COMMAND';
    public const ROLE_GENERATE_CONFIGURATION = 'ROLE_GENERATE_CONFIGURATION';
    public const ROLE_MANAGE_TOKENS = 'ROLE_MANAGE_TOKENS';
    public const ROLE_CREATE_EDIT_POLLER_CFG = 'CREATE_EDIT_POLLER_CFG';
    public const ROLE_DELETE_POLLER_CFG = 'DELETE_POLLER_CFG';
    public const ROLE_DISPLAY_TOP_COUNTER = 'DISPLAY_TOP_COUNTER';
    public const ROLE_DISPLAY_TOP_COUNTER_POLLERS_STATISTICS = 'DISPLAY_TOP_COUNTER_POLLERS_STATISTICS';

    // user pages access
    public const ROLE_HOME_DASHBOARD_VIEWER = 'ROLE_HOME_DASHBOARDS_VIEWER_RW';
    public const ROLE_HOME_DASHBOARD_CREATOR = 'ROLE_HOME_DASHBOARDS_CREATOR_RW';
    public const ROLE_HOME_DASHBOARD_ADMIN = 'ROLE_HOME_DASHBOARDS_ADMINISTRATOR_RW';
    public const ROLE_CONFIGURATION_HOSTS_WRITE = 'ROLE_CONFIGURATION_HOSTS_HOSTS_RW';
    public const ROLE_CONFIGURATION_HOSTS_READ = 'ROLE_CONFIGURATION_HOSTS_HOSTS_R';
    public const ROLE_CONFIGURATION_SERVICES_WRITE = 'ROLE_CONFIGURATION_SERVICES_SERVICES_BY_HOST_RW';
    public const ROLE_CONFIGURATION_SERVICES_READ = 'ROLE_CONFIGURATION_SERVICES_SERVICES_BY_HOST_R';
    public const ROLE_CONFIGURATION_META_SERVICES_WRITE = 'ROLE_CONFIGURATION_SERVICES_META_SERVICES_RW';
    public const ROLE_CONFIGURATION_META_SERVICES_READ = 'ROLE_CONFIGURATION_SERVICES_META_SERVICES_R';
    public const ROLE_MONITORING_EVENT_LOGS = 'ROLE_MONITORING_EVENT_LOGS_EVENT_LOGS_RW';
    public const ROLE_REPORTING_AVAILABILITY_HOSTS = 'ROLE_REPORTING_AVAILABILITY_HOSTS_RW';
    public const ROLE_REPORTING_AVAILABILITY_SERVICES = 'ROLE_REPORTING_AVAILABILITY_SERVICES_RW';
    public const ROLE_CONFIGURATION_MONITORING_SERVER_READ_WRITE = 'ROLE_CONFIGURATION_POLLERS_POLLERS_RW';
    public const ROLE_CONFIGURATION_MONITORING_SERVER_READ = 'ROLE_CONFIGURATION_POLLERS_POLLERS_R';
    public const ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ_WRITE = 'ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_RW';
    public const ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ = 'ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_R';
    public const ROLE_CONFIGURATION_SERVICES_SERVICE_GROUPS_READ_WRITE = 'ROLE_CONFIGURATION_SERVICES_SERVICE_GROUPS_RW';
    public const ROLE_CONFIGURATION_SERVICES_SERVICE_GROUPS_READ = 'ROLE_CONFIGURATION_SERVICES_SERVICE_GROUPS_R';
    public const ROLE_CONFIGURATION_CONTACTS_READ_WRITE = 'ROLE_CONFIGURATION_USERS_CONTACTS__USERS_RW';
    public const ROLE_CONFIGURATION_CONTACTS_READ = 'ROLE_CONFIGURATION_USERS_CONTACTS__USERS_R';
    public const ROLE_CONFIGURATION_USERS_CONTACT_GROUPS_READ_WRITE = 'ROLE_CONFIGURATION_USERS_CONTACT_GROUPS_RW';
    public const ROLE_CONFIGURATION_CONTACT_TEMPLATES_READ = 'ROLE_CONFIGURATION_USERS_CONTACT_TEMPLATES_R';
    public const ROLE_CONFIGURATION_CONTACT_TEMPLATES_READ_WRITE = 'ROLE_CONFIGURATION_USERS_CONTACT_TEMPLATES_RW';
    public const ROLE_CONFIGURATION_USERS_CONTACT_GROUPS_READ = 'ROLE_CONFIGURATION_USERS_CONTACT_GROUPS_R';
    public const ROLE_CONFIGURATION_TIME_PERIODS_READ_WRITE = 'ROLE_CONFIGURATION_USERS_TIME_PERIODS_RW';
    public const ROLE_CONFIGURATION_TIME_PERIODS_READ = 'ROLE_CONFIGURATION_USERS_TIME_PERIODS_R';
    public const ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ = 'ROLE_CONFIGURATION_HOSTS_CATEGORIES_R';
    public const ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE = 'ROLE_CONFIGURATION_HOSTS_CATEGORIES_RW';
    public const ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE = 'ROLE_CONFIGURATION_SERVICES_CATEGORIES_RW';
    public const ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ = 'ROLE_CONFIGURATION_SERVICES_CATEGORIES_R';
    public const ROLE_ADMINISTRATION_AUTHENTICATION_READ_WRITE = 'ROLE_ADMINISTRATION_AUTHENTICATION_RW';
    public const ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE = 'ROLE_CONFIGURATION_NOTIFICATIONS_RW';
    public const ROLE_CONFIGURATION_HOSTS_TEMPLATES_READ_WRITE = 'ROLE_CONFIGURATION_HOSTS_TEMPLATES_RW';
    public const ROLE_CONFIGURATION_HOSTS_TEMPLATES_READ = 'ROLE_CONFIGURATION_HOSTS_TEMPLATES_R';
    public const ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ_WRITE = 'ROLE_CONFIGURATION_SERVICES_TEMPLATES_RW';
    public const ROLE_CONFIGURATION_SERVICES_TEMPLATES_READ = 'ROLE_CONFIGURATION_SERVICES_TEMPLATES_R';
    public const ROLE_CONFIGURATION_COMMANDS_CHECKS_R = 'ROLE_CONFIGURATION_COMMANDS_CHECKS_R';
    public const ROLE_CONFIGURATION_COMMANDS_CHECKS_RW = 'ROLE_CONFIGURATION_COMMANDS_CHECKS_RW';
    public const ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_R = 'ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_R';
    public const ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_RW = 'ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_RW';
    public const ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_R = 'ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_R';
    public const ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_RW = 'ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_RW';
    public const ROLE_CONFIGURATION_COMMANDS_DISCOVERY_R = 'ROLE_CONFIGURATION_COMMANDS_DISCOVERY_R';
    public const ROLE_CONFIGURATION_COMMANDS_DISCOVERY_RW = 'ROLE_CONFIGURATION_COMMANDS_DISCOVERY_RW';
    public const ROLE_CONFIGURATION_GRAPH_TEMPLATES_R = 'ROLE_MONITORING_PERFORMANCES_TEMPLATES_R';
    public const ROLE_CONFIGURATION_GRAPH_TEMPLATES_RW = 'ROLE_MONITORING_PERFORMANCES_TEMPLATES_RW';
    public const ROLE_CONFIGURATION_CONNECTORS_R = 'ROLE_CONFIGURATION_COMMANDS_CONNECTORS_R';
    public const ROLE_CONFIGURATION_CONNECTORS_RW = 'ROLE_CONFIGURATION_COMMANDS_CONNECTORS_RW';
    public const ROLE_ADMINISTRATION_AUTHENTICATION_TOKENS_RW = 'ROLE_ADMINISTRATION_AUTHENTICATION_TOKENS_RW';
    public const ROLE_ADMINISTRATION_PARAMETERS_IMAGES_RW = 'ROLE_ADMINISTRATION_PARAMETERS_IMAGES_RW';
    public const ROLE_ADMINISTRATION_ACL_RESOURCE_ACCESS_MANAGEMENT_RW = 'ROLE_ADMINISTRATION_ACL_RESOURCE_ACCESS_MANAGEMENT_RW';
    public const ROLE_ADMINISTRATION_PARAMETERS_MONITORING_RW = 'ROLE_ADMINISTRATION_PARAMETERS_MONITORING_RW';
    public const ROLE_MONITORING_RESOURCES_STATUS_RW = 'ROLE_MONITORING_RESOURCES_STATUS_RW';
    public const ROLE_CONFIGURATION_BROKER_RW = 'ROLE_CONFIGURATION_POLLERS_BROKER_CONFIGURATION_RW';
    public const ROLE_MONITORING_PERFORMANCES_RW = 'ROLE_MONITORING_PERFORMANCES_RW';
    public const ROLE_MONITORING_RW = 'ROLE_MONITORING_RW';

    public const ROLE_CONFIGURATION_ACC_RW = 'ROLE_CONFIGURATION_CONNECTORS_ADDITIONAL_CONFIGURATIONS_RW';
    public const ROLE_CONFIGURATION_POLLERS_AGENT_CONFIGURATIONS_RW = 'ROLE_CONFIGURATION_POLLERS_AGENT_CONFIGURATIONS_RW';

    /**
     * @var string
     */
    public const DEFAULT_LOCALE = 'en_US';

    /**
     * @var string
     */
    public const DEFAULT_CHARSET = 'UTF-8';

    /**
     * @var int Id of contact
     */
    private $id;

    /**
     * @var string Name of contact
     */
    private $name;

    /**
     * @var string Alias of contact
     */
    private $alias;

    /**
     * @var string Language of contact
     */
    private $lang;

    /**
     * @var string Email of contact
     */
    private $email;

    /**
     * @var bool Is an admin contact ?
     */
    private $isAdmin;

    /**
     * @var int|null Id of the contact template
     */
    private $templateId;

    /**
     * @var bool Indicates whether this contact is enabled or disabled
     */
    private $isActive;

    /**
     * @var bool Indicates whether this contact is allowed to reach centreon application
     */
    private $isAllowedToReachWeb;

    /**
     * @var string|null Authentication Token
     */
    private $token;

    /**
     * @var string|null Encoded password
     */
    private $encodedPassword;

    /**
     * @var bool Indicates if this user has access to the configuration section of API
     */
    private $hasAccessToApiConfiguration;

    /**
     * @var bool Indicates if this user has access to the real time section of API
     */
    private $hasAccessToApiRealTime;

    /**
     * @var string[]
     */
    private $roles = [];

    /**
     * @var string[] List of names of topology rules to which the contact can access
     */
    private $topologyRulesNames = [];

    /**
     * @var \DateTimeZone $timezone timezone of the user
     */
    private $timezone;

    /**
     * @var int
     */
    private int $timezoneId;

    /**
     * @var string|null $locale locale of the user
     */
    private $locale;

    /**
     * @var Page|null
     */
    private $defaultPage;

    /**
     * Indicates if user uses deprecated pages
     *
     * @var bool
     */
    private $useDeprecatedPages;

    private bool $useDeprecatedCustomViews;

    /**
     * @var string|null
     */
    private $theme;

    /** @var string|null */
    private $userInterfaceDensity;

    /**
     * @param int $timezoneId
     *
     * @return self
     */
    public function setTimezoneId(int $timezoneId): self
    {
        $this->timezoneId = $timezoneId;

        return $this;
    }

    /**
     * @return int
     */
    public function getTimezoneId(): int
    {
        return $this->timezoneId;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return self
     */
    public function setId(int $id): self
    {
        $this->id = $id;

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
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
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
     * @return self
     */
    public function setAlias(string $alias): self
    {
        $this->alias = $alias;

        return $this;
    }

    /**
     * @return string
     */
    public function getLang(): string
    {
        return $this->lang;
    }

    /**
     * @param string $lang
     *
     * @return self
     */
    public function setLang(string $lang): self
    {
        $this->lang = $lang;

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
     * @return self
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    /**
     * Set if the user is admin or not.
     *
     * @param bool $isAdmin
     *
     * @return self
     */
    public function setAdmin(bool $isAdmin): self
    {
        $this->isAdmin = $isAdmin;
        if ($this->isAdmin) {
            $this->addRole(self::ROLE_API_REALTIME);
            $this->addRole(self::ROLE_API_CONFIGURATION);
        }

        return $this;
    }

    /**
     * @return int|null
     */
    public function getTemplateId(): ?int
    {
        return $this->templateId;
    }

    /**
     * @param int|null $templateId
     *
     * @return self
     */
    public function setTemplateId(?int $templateId): self
    {
        $this->templateId = $templateId;

        return $this;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @param bool $isActive
     *
     * @return self
     */
    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isAllowedToReachWeb(): bool
    {
        return $this->isAllowedToReachWeb;
    }

    /**
     * @inheritDoc
     */
    public function setAllowedToReachWeb(bool $isAllowed): static
    {
        $this->isAllowedToReachWeb = $isAllowed;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * @param string|null $token
     *
     * @return self
     */
    public function setToken(?string $token): self
    {
        $this->token = $token;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getEncodedPassword(): ?string
    {
        return $this->encodedPassword;
    }

    /**
     * @param string|null $encodedPassword
     *
     * @return self
     */
    public function setEncodedPassword(?string $encodedPassword): self
    {
        $this->encodedPassword = $encodedPassword;

        return $this;
    }

    /**
     * Returns the roles granted to the user.
     *
     *     public function getRoles()
     *     {
     *         return array('ROLE_USER');
     *     }
     *
     * Alternatively, the roles might be stored on a ``roles`` property,
     * and populated in any number of different ways when the user object
     * is created.
     *
     * @return string[] The user roles
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @inheritDoc
     */
    public function setRoles(array $roles): self
    {
        $this->roles = [];
        foreach ($roles as $role) {
            $this->addRole($role);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getTopologyRules(): array
    {
        return $this->topologyRulesNames;
    }

    /**
     * @inheritDoc
     */
    public function setTopologyRules(array $topologyRoles): self
    {
        $this->topologyRulesNames = [];
        foreach ($topologyRoles as $topologyRole) {
            $this->addTopologyRule($topologyRole);
        }

        return $this;
    }

    /**
     * Returns the password used to authenticate the user.
     *
     * This should be the encoded password. On authentication, a plain-text
     * password will be salted, encoded, and then compared to this value.
     *
     * @return string|null The password
     */
    public function getPassword(): ?string
    {
        return $this->token;
    }

    /**
     * Returns the salt that was originally used to encode the password.
     *
     * This can return null if the password was not encoded using a salt.
     *
     * @return string|null The salt
     */
    public function getSalt()
    {
        return null;
    }

    /**
     * Returns the username used to authenticate the user.
     *
     * @return string The username
     */
    public function getUsername()
    {
        return $this->name;
    }

    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     */
    public function eraseCredentials(): void
    {
        // Nothing to do. But we must to define this method
    }

    /**
     * @inheritDoc
     */
    public function hasAccessToApiConfiguration(): bool
    {
        return $this->hasAccessToApiConfiguration;
    }

    /**
     * @inheritDoc
     */
    public function setAccessToApiConfiguration(bool $hasAccessToApiConfiguration): static
    {
        $this->hasAccessToApiConfiguration = $hasAccessToApiConfiguration;

        if ($this->hasAccessToApiConfiguration) {
            $this->addRole(self::ROLE_API_CONFIGURATION);
        } else {
            $this->removeRole(self::ROLE_API_CONFIGURATION);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function hasAccessToApiRealTime(): bool
    {
        return $this->hasAccessToApiRealTime;
    }

    /**
     * @inheritDoc
     */
    public function setAccessToApiRealTime(bool $hasAccessToApiRealTime): static
    {
        $this->hasAccessToApiRealTime = $hasAccessToApiRealTime;
        if ($this->hasAccessToApiRealTime) {
            $this->addRole(self::ROLE_API_REALTIME);
        } else {
            $this->removeRole(self::ROLE_API_REALTIME);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles);
    }

    /**
     * @inheritDoc
     */
    public function hasTopologyRole(string $role): bool
    {
        return in_array($role, $this->topologyRulesNames);
    }

    /**
     * Add a specific role to this user.
     *
     * @param string $roleName Role name to add
     */
    public function addRole(string $roleName): void
    {
        if (! in_array($roleName, $this->roles)) {
            $this->roles[] = $roleName;
        }
    }

    /**
     * Removes an existing roles.
     *
     * @param string $roleName Role name to remove
     */
    private function removeRole(string $roleName): void
    {
        if (($index = array_search($roleName, $this->roles)) !== false) {
            unset($this->roles[$index]);
        }
    }

    /**
     * Added a topology rule.
     *
     * @param string $topologyRuleName Topology rule name
     */
    public function addTopologyRule(string $topologyRuleName): void
    {
        if (! in_array($topologyRuleName, $this->topologyRulesNames)) {
            $this->topologyRulesNames[] = $topologyRuleName;
        }
    }

    /**
     * timezone setter
     *
     * @param \DateTimeZone $timezone
     *
     * @return self
     */
    public function setTimezone(\DateTimeZone $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * timezone getter
     *
     * @return \DateTimeZone
     */
    public function getTimezone(): \DateTimeZone
    {
        return $this->timezone;
    }

    /**
     * locale setter
     *
     * @param string|null $locale
     *
     * @return self
     */
    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * locale getter
     *
     * @return string|null
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * @inheritDoc
     */
    public function setDefaultPage(?Page $defaultPage): static
    {
        $this->defaultPage = $defaultPage;

        return $this;
    }

    /**
     * get user default page
     *
     * @return Page|null
     */
    public function getDefaultPage(): ?Page
    {
        return $this->defaultPage;
    }

    /**
     * @inheritDoc
     */
    public function isUsingDeprecatedPages(): bool
    {
        return $this->useDeprecatedPages;
    }

    /**
     * @inheritDoc
     */
    public function setUseDeprecatedPages(bool $useDeprecatedPages): static
    {
        $this->useDeprecatedPages = $useDeprecatedPages;

        return $this;
    }

    public function isUsingDeprecatedCustomViews(): bool
    {
        return $this->useDeprecatedCustomViews;
    }

    public function setUseDeprecatedCustomViews(bool $useDeprecatedCustomViews): static
    {
        $this->useDeprecatedCustomViews = $useDeprecatedCustomViews;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getUserIdentifier(): string
    {
        return $this->alias;
    }

    /**
     * Set user current theme.
     *
     * @param string $theme user's new theme.
     *
     * @return self
     */
    public function setTheme(string $theme): self
    {
        $this->theme = $theme;

        return $this;
    }

    /**
     * Get user current theme.
     *
     * @return string|null
     */
    public function getTheme(): ?string
    {
        return $this->theme;
    }

    /**
     * @param string|null $userInterfaceDensity
     *
     * @return $this
     */
    public function setUserInterfaceDensity(?string $userInterfaceDensity): self
    {
        $this->userInterfaceDensity = $userInterfaceDensity;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getUserInterfaceDensity(): ?string
    {
        return $this->userInterfaceDensity;
    }

    /**
     * @return string
     */
    public function getFormatDate(): string
    {
        $dateFormatter = new \IntlDateFormatter(
            $this->getLocale(),
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::SHORT,
            $this->getTimezone()
        );
        $format = $dateFormatter->getPattern();

        return $this->convertIntlPatternToDateTimeFormat($format);
    }

    /**
     * IntlDateFormatter used a different format than DateTime::format.
     * This method converts IntlDateFormatter pattern to DateTime format.
     *
     * @param string $intlPattern
     *
     * @return string
     */
    private function convertIntlPatternToDateTimeFormat(string $intlPattern): string
    {
        $intlToPhp = [
            'yyyy' => 'Y',
            'yy' => 'y',
            'MMMM' => 'F',
            'MMM' => 'M',
            'MM' => 'm',
            'M' => 'n',
            'dd' => 'd',
            'd' => 'j',
            'EEEE' => 'l',
            'EEE' => 'D',
            'HH' => 'H',
            'H' => 'G',
            'hh' => 'h',
            'h' => 'g',
            'mm' => 'i',
            'ss' => 's',
            'SSS' => 'u',
            'a' => 'A',
            'zzzz' => 'e',
            'zzz' => 'T',
            'zz' => 'T',
            'z' => 'T',
        ];

        return strtr($intlPattern, $intlToPhp);
    }
}
