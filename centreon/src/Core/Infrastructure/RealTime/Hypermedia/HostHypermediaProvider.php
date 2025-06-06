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

namespace Core\Infrastructure\RealTime\Hypermedia;

use Centreon\Domain\Contact\Contact;
use Core\Domain\RealTime\Model\ResourceTypes\HostResourceType;

class HostHypermediaProvider extends AbstractHypermediaProvider implements HypermediaProviderInterface
{
    public const URI_CONFIGURATION = '/main.php?p=60101&o=c&host_id={hostId}';
    public const URI_EVENT_LOGS = '/main.php?p=20301&h={hostId}';
    public const URI_REPORTING = '/main.php?p=307&host={hostId}';
    public const URI_HOST_CATEGORY_CONFIGURATION = '/main.php?p=60104&o=c&hc_id={hostCategoryId}';
    public const ENDPOINT_HOST_ACKNOWLEDGEMENT = 'centreon_application_acknowledgement_addhostacknowledgement';
    public const ENDPOINT_DETAILS = 'centreon_application_monitoring_resource_details_host';
    public const ENDPOINT_HOST_CHECK = 'centreon_application_check_checkHost';
    public const ENDPOINT_SERVICE_DOWNTIME = 'monitoring.downtime.addHostDowntime';
    public const ENDPOINT_HOST_NOTIFICATION_POLICY = 'configuration.host.notification-policy';
    public const ENDPOINT_HOST_TIMELINE = 'centreon_application_monitoring_gettimelinebyhost';
    public const ENDPOINT_HOST_TIMELINE_DOWNLOAD = 'centreon_application_monitoring_download_timeline_by_host';
    public const ENDPOINT_HOSTGROUP_CONFIGURATION = 'GetHostGroup';

    /**
     * @inheritDoc
     */
    public function isValidFor(string $resourceType): bool
    {
        return $resourceType === HostResourceType::TYPE_NAME;
    }

    /**
     * @inheritDoc
     */
    public function createEndpoints(array $parameters): array
    {
        $urlParams = ['hostId' => $parameters['hostId']];

        return [
            'timeline' => $this->generateEndpoint(self::ENDPOINT_HOST_TIMELINE, $urlParams),
            'timeline_download' => $this->generateEndpoint(self::ENDPOINT_HOST_TIMELINE_DOWNLOAD, $urlParams),
            'notification_policy' => $this->generateEndpoint(
                self::ENDPOINT_HOST_NOTIFICATION_POLICY,
                $urlParams
            ),
            'details' => $this->generateEndpoint(self::ENDPOINT_DETAILS, $urlParams),
            'downtime' => $this->generateDowntimeEndpoint($urlParams),
            'acknowledgement' => $this->generateAcknowledgementEndpoint($urlParams),
            'check' => $this->generateCheckEndpoint($urlParams),
            'forced_check' => $this->generateForcedCheckEndpoint($urlParams),
        ];
    }

    /**
     * @inheritDoc
     */
    public function createForConfiguration(array $parameters): ?string
    {
        $roles = [
            Contact::ROLE_CONFIGURATION_HOSTS_WRITE,
            Contact::ROLE_CONFIGURATION_HOSTS_READ,
        ];

        if (! $this->canContactAccessPages($this->contact, $roles)) {
            return null;
        }

        return $this->generateUri(self::URI_CONFIGURATION, ['{hostId}' => $parameters['hostId']]);
    }

    /**
     * @inheritDoc
     */
    public function createForReporting(array $parameters): ?string
    {
        if (! $this->canContactAccessPages($this->contact, [Contact::ROLE_REPORTING_AVAILABILITY_HOSTS])) {
            return null;
        }

        return $this->generateUri(self::URI_REPORTING, ['{hostId}' => $parameters['hostId']]);
    }

    /**
     * @inheritDoc
     */
    public function createForEventLog(array $parameters): ?string
    {
        $urlParams = ['{hostId}' => $parameters['hostId']];

        return $this->createUrlForEventLog($urlParams);
    }

    /**
     * Create hostgroup configuration redirection uri.
     *
     * @param array<string, mixed> $parameters
     *
     * @return string|null
     */
    public function generateHostGroupEndpoint(array $parameters): ?string
    {
        $roles = [
            Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ_WRITE,
            Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ,
        ];

        if (! $this->canContactAccessPages($this->contact, $roles)) {
            return null;
        }

        return $this->generateEndpoint(
            self::ENDPOINT_HOSTGROUP_CONFIGURATION,
            ['hostGroupId' => $parameters['hostgroupId']]
        );
    }

    /**
     * Create host category configuration redirection uri.
     *
     * @param array<string, mixed> $parameters
     *
     * @return string|null
     */
    public function createForCategory(array $parameters): ?string
    {
        $roles = [
            Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE,
            Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ,
        ];

        if (! $this->canContactAccessPages($this->contact, $roles)) {
            return null;
        }

        return $this->generateUri(
            self::URI_HOST_CATEGORY_CONFIGURATION,
            ['{hostCategoryId}' => $parameters['categoryId']]
        );
    }

    /**
     * @inheritDoc
     */
    public function convertGroupsForPresenter(array $groups): array
    {
        return array_map(
            fn (array $group) => [
                'id' => $group['id'],
                'name' => $group['name'],
                'configuration_endpoint' => $this->generateHostGroupEndpoint(['hostgroupId' => $group['id']]),
            ],
            $groups
        );
    }

    /**
     * @inheritDoc
     */
    public function convertCategoriesForPresenter(array $categories): array
    {
        return array_map(
            fn (array $category) => [
                'id' => $category['id'],
                'name' => $category['name'],
                'configuration_uri' => $this->createForCategory(['categoryId' => $category['id']]),
            ],
            $categories
        );
    }

    /**
     * @param array<string, int> $parameters
     *
     * @return string
     */
    private function generateAcknowledgementEndpoint(array $parameters): string
    {
        $acknowledgementFilter = ['limit' => 1];

        return $this->generateEndpoint(
            self::ENDPOINT_HOST_ACKNOWLEDGEMENT,
            array_merge($parameters, $acknowledgementFilter)
        );
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return string|null
     */
    private function generateCheckEndpoint(array $parameters): ?string
    {
        return ($this->contact->hasRole(Contact::ROLE_HOST_CHECK) || $this->contact->isAdmin())
            ? $this->generateEndpoint(self::ENDPOINT_HOST_CHECK, $parameters)
            : null;
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return string|null
     */
    private function generateForcedCheckEndpoint(array $parameters): ?string
    {
        return ($this->contact->hasRole(Contact::ROLE_HOST_FORCED_CHECK) || $this->contact->isAdmin())
            ? $this->generateEndpoint(self::ENDPOINT_HOST_CHECK, $parameters)
            : null;
    }
}
