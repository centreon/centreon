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

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/centreonUUID.class.php';
require_once __DIR__ . '/centreonGMT.class.php';
require_once __DIR__ . '/centreonVersion.class.php';
require_once __DIR__ . '/centreonDB.class.php';
require_once __DIR__ . '/centreonStatsModules.class.php';

use App\Kernel;
use Core\Common\Infrastructure\FeatureFlags;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Class
 *
 * @class CentreonStatistics
 */
class CentreonStatistics
{
    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /** @var CentreonDB */
    private CentreonDB $dbConfig;

    /** @var FeatureFlags|null */
    private ?FeatureFlags $featureFlags;

    /**
     * CentreonStatistics constructor
     *
     * @param LoggerInterface $logger
     *
     * @throws LogicException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->dbConfig = new CentreonDB();
        $this->logger = $logger;

        $kernel = Kernel::createForWeb();
        $this->featureFlags = $kernel->getContainer()->get(FeatureFlags::class);
    }

    /**
     * get Centreon UUID
     *
     * @return array
     */
    public function getCentreonUUID()
    {
        $centreonUUID = new CentreonUUID($this->dbConfig);
        return [
            'CentreonUUID' => $centreonUUID->getUUID()
        ];
    }

    /**
     * get Centreon information
     *
     * @return array
     * @throws PDOException
     */
    public function getPlatformInfo()
    {
        $query = <<<'SQL'
            SELECT
                COUNT(h.host_id) as nb_hosts,
                ( SELECT COUNT(hg.hg_id) FROM hostgroup hg WHERE hg.hg_activate = '1' ) as nb_hg,
                ( SELECT COUNT(s.service_id) FROM service s WHERE s.service_activate = '1' AND s.service_register = '1' ) as nb_services,
                ( SELECT COUNT(sg.sg_id) FROM servicegroup sg WHERE sg.sg_activate = '1' ) as nb_sg,
                @nb_remotes:=( SELECT COUNT(ns.id) FROM nagios_server ns, remote_servers rs
                               WHERE ns.ns_activate = '1' AND rs.server_id = ns.id ) as nb_remotes,
                ( ( SELECT COUNT(ns2.id) FROM nagios_server ns2 WHERE ns2.ns_activate = '1' )-@nb_remotes-1 ) as nb_pollers,
                '1' as nb_central
            FROM host h
            WHERE h.host_activate = '1' AND h.host_register = '1'
            SQL;

        return $this->dbConfig->query($query)->fetch();
    }

    /**
     * get version of Centreon Web
     *
     * @return array
     * @throws Exception
     */
    public function getVersion()
    {
        $dbStorage = new CentreonDB("centstorage");
        $centreonVersion = new CentreonVersion($this->dbConfig, $dbStorage);
        return [
            'core' => $centreonVersion->getCore(),
            'modules' => $centreonVersion->getModules(),
            'widgets' => $centreonVersion->getWidgets(),
            'system' => $centreonVersion->getSystem(),
        ];
    }

    /**
     * get Centreon timezone
     *
     * @return array
     */
    public function getPlatformTimezone()
    {
        $oTimezone = new CentreonGMT($this->dbConfig);
        $defaultTimezone = $oTimezone->getCentreonTimezone();
        $timezoneById = $oTimezone->getList();

        if (!empty($defaultTimezone) && !empty($timezoneById[$defaultTimezone])) {
            $timezone = $timezoneById[$defaultTimezone];
        } else {
            $timezone = date_default_timezone_get();
        }

        return [
            'timezone' => $timezone
        ];
    }

    /**
     * get LDAP configured authentications options
     *
     * @return array
     * @throws PDOException
     */
    public function getLDAPAuthenticationOptions()
    {
        $data = [];

        # Get the number of LDAP directories configured by LDAP configuration
        $query = <<<'SQL'
            SELECT ar.ar_id, COUNT(arh.auth_ressource_id) AS configured_ad
            FROM auth_ressource_host AS arh
            INNER JOIN auth_ressource AS ar ON (arh.auth_ressource_id = ar.ar_id)
            WHERE ar.ar_enable = '1'
            GROUP BY ar_id
            SQL;
        $result = $this->dbConfig->query($query);
        while ($row = $result->fetch()) {
            $data[$row['ar_id']] = [
                "nb_ar_servers" => $row['configured_ad']
            ];
        }

        # Get configured options by LDAP configuration
        $query = <<<'SQL'
            SELECT ar.ar_id, ari.ari_name, ari.ari_value
            FROM auth_ressource_host AS arh
            INNER JOIN auth_ressource AS ar ON (arh.auth_ressource_id = ar.ar_id)
            INNER JOIN auth_ressource_info AS ari ON (ari.ar_id = ar.ar_id)
            WHERE ari.ari_name IN ('ldap_template', 'ldap_auto_sync', 'ldap_sync_interval', 'ldap_auto_import',
                'ldap_search_limit', 'ldap_search_timeout', 'ldap_srv_dns', 'ldap_store_password', 'protocol_version')
            SQL;
        $result = $this->dbConfig->query($query);
        while ($row = $result->fetch()) {
            $data[$row['ar_id']][$row['ari_name']] = $row['ari_value'];
        }

        return $data;
    }

    /**
     * get Local / SSO configured authentications options
     *
     * @return array
     * @throws PDOException
     */
    public function getNewAuthenticationOptions()
    {
        $data = [];

        // Get authentication configuration
        $query = "SELECT * FROM provider_configuration WHERE is_active = '1'";
        $result = $this->dbConfig->query($query);
        while ($row = $result->fetch()) {
            $customConfiguration = json_decode($row['custom_configuration'], true);
            switch ($row['type']) {
                case 'local':
                    $data['local'] = $customConfiguration['password_security_policy'];
                    break;
                case 'web-sso':
                    $data['web-sso'] = [
                        'is_forced' => (bool)$row['is_forced'],
                        'trusted_client_addresses' => count($customConfiguration['trusted_client_addresses'] ?? []),
                        'blacklist_client_addresses'
                            => count($customConfiguration['blacklist_client_addresses'] ?? []),
                        'pattern_matching_login' => (bool)$customConfiguration['pattern_matching_login'],
                        'pattern_replace_login' => (bool)$customConfiguration['pattern_replace_login'],
                    ];
                    break;
                case 'openid':
                    $authenticationConditions = $customConfiguration['authentication_conditions'];
                    $groupsMapping = $customConfiguration['groups_mapping'];
                    $rolesMapping = $customConfiguration['roles_mapping'];
                    $data['openid'] = [
                        'is_forced' => (bool)$row['is_forced'],
                        'authenticationConditions' => [
                            'is_enabled' => (bool)$authenticationConditions['is_enabled'],
                            'trusted_client_addresses'
                                => count($authenticationConditions['trusted_client_addresses'] ?? []),
                            'blacklist_client_addresses'
                                => count($authenticationConditions['blacklist_client_addresses'] ?? []),
                            'authorized_values' => count($authenticationConditions['authorized_values'] ?? [])
                        ],
                        'groups_mapping' => [
                            'is_enabled' => (bool)$groupsMapping['is_enabled'],
                            'relations' => $this->getContactGroupRelationsByProviderType('openid')
                        ],
                        'roles_mapping' => [
                            'is_enabled' => (bool)$rolesMapping['is_enabled'],
                            'apply_only_first_role' => (bool)$rolesMapping['apply_only_first_role'],
                            'relations' => $this->getAclRelationsByProviderType('openid'),
                        ],
                        'introspection_token_endpoint' => (bool)$customConfiguration['introspection_token_endpoint'],
                        'userinfo_endpoint' => (bool)$customConfiguration['userinfo_endpoint'],
                        'endsession_endpoint' => (bool)$customConfiguration['endsession_endpoint'],
                        'connection_scopes' => count($customConfiguration['connection_scopes'] ?? []),
                        'authentication_type' => $customConfiguration['authentication_type'],
                        'verify_peer' => (bool)$customConfiguration['verify_peer'],
                        'auto_import' => (bool)$customConfiguration['auto_import'],
                        'redirect_url' => $customConfiguration['redirect_url'] !== null
                    ];
                    break;
                case 'saml':
                    $authenticationConditions = $customConfiguration['authentication_conditions'];
                    $groupsMapping = $customConfiguration['groups_mapping'];
                    $rolesMapping = $customConfiguration['roles_mapping'];
                    $data['saml'] = [
                        'is_forced' => (bool)$row['is_forced'],
                        'authenticationConditions' => [
                            'is_enabled' => (bool)$authenticationConditions['is_enabled'],
                            'authorized_values' => count($authenticationConditions['authorized_values'] ?? [])
                        ],
                        'groups_mapping' => [
                            'is_enabled' => (bool)$groupsMapping['is_enabled'],
                            'relations' => $this->getContactGroupRelationsByProviderType('saml'),
                        ],
                        'roles_mapping' => [
                            'is_enabled' => (bool)$rolesMapping['is_enabled'],
                            'apply_only_first_role' => (bool)$rolesMapping['apply_only_first_role'],
                            'relations' => $this->getAclRelationsByProviderType('saml'),
                        ],
                        'auto_import' => (bool)$customConfiguration['auto_import'],
                        'logout_from' => (bool)$customConfiguration['logout_from'] === true ?
                            'Only Centreon' :
                            'Centreon + Idp'
                    ];
                    break;
            }
        }

        return $data;
    }

    /**
     * get configured authentications options
     *
     * @return array
     * @throws PDOException
     */
    public function getAuthenticationOptions()
    {
        $data = $this->getNewAuthenticationOptions();
        $data['LDAP'] = $this->getLDAPAuthenticationOptions();

        return $data;
    }

    /**
     * get info about manually managed API tokens
     *
     * @return array
     * @throws PDOException
     */
    public function getApiTokensInfo()
    {
        $data = [];

        $statementNbTokens = $this->dbConfig->query(
            <<<'SQL'
                SELECT count(token_type)
                FROM security_authentication_tokens
                WHERE token_type ='manual'
                SQL
        );
        $data['total'] = $statementNbTokens->fetch(PDO::FETCH_COLUMN) ?: 0;

        $statementNbTokens = $this->dbConfig->query(
            <<<'SQL'
                SELECT count(contact_id)
                FROM contact
                WHERE
                    contact_id IN (
                        SELECT contact_id
                        FROM contact
                        WHERE
                            contact_admin = '1'
                            AND contact_name != 'centreon-gorgone'

                    )
                    OR contact_id IN (
                        SELECT contact_id
                        FROM contact
                        JOIN acl_group_contacts_relations acl_grp_contact_rel
                            ON contact.contact_id = acl_grp_contact_rel.contact_contact_id
                        JOIN acl_group_actions_relations acl_grp_action_rel
                            ON acl_grp_contact_rel.acl_group_id = acl_grp_action_rel.acl_group_id
                        JOIN acl_actions_rules acl_action
                            ON acl_grp_action_rel.acl_action_id = acl_action.acl_action_rule_id
                            AND acl_action.acl_action_name = 'manage_tokens'
                    )
                SQL
        );
        $data['managers'] = $statementNbTokens->fetch(PDO::FETCH_COLUMN) ?: 0;

        return $data;
    }

    /**
     * Get Additional data
     *
     * @return array
     */
    public function getAdditionalData()
    {
        $centreonVersion = new CentreonVersion($this->dbConfig);

        $data = [
            'extension' => [
                'widgets' => $centreonVersion->getWidgetsUsage()
            ],
        ];

        $oModulesStats = new CentreonStatsModules($this->logger);
        $modulesData = $oModulesStats->getModulesStatistics();
        foreach ($modulesData as $moduleData) {
            $data['extension'] = array_merge($data['extension'], $moduleData);
        }

        if ($this->featureFlags?->isEnabled('notification')) {
            $data['notification'] = $this->getAdditionalNotificationInformation();
        }

        if ($this->featureFlags?->isEnabled('dashboard')) {
            $data['dashboards'] = $this->getAdditionalDashboardsInformation();
        }

        if ($this->featureFlags?->isEnabled('dashboard_playlist')) {
            $data['playlists'] = $this->getAdditionalDashboardPlaylistsInformation();
        }

        $data['user_filter'] = $this->getAdditionalUserFiltersInformation();

        return $data;
    }

    /**
     * Get additional connector configurations data.
     *
     * @return array
     */
    public function getAccData(): array
    {
        $data = ['total' => 0];

        $statement = $this->dbConfig->executeQuery(
            <<<'SQL'
                SELECT type, count(id) as value
                FROM additional_connector_configuration
                GROUP BY type
                UNION SELECT 'total' as type, count(id) as value
                FROM additional_connector_configuration
                SQL
        );

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $data[$row['type']] = $row['value'];
        }

        return $data;
    }

    /**
     * @return array{
     *     total: int,
     *     avg_hg_notification?: float,
     *     avg_sg_notification?: float,
     *     avg_bv_notification?: float,
     *     avg_contact_notification?: float,
     *     avg_cg_notification?: float
     * }
     * @throws PDOException
     */
    private function getAdditionalNotificationInformation(): array
    {
        $data = [];

        $avgGetValue = function (string $tableRelation): float|null {
            $sqlAverage = <<<SQL
                SELECT AVG(nb) FROM (
                    SELECT COUNT(id) as nb, n.id
                    FROM {$tableRelation} rel
                    INNER JOIN notification n ON n.id=rel.notification_id AND n.is_activated=1
                    GROUP BY n.id
                ) tmp
                SQL;

            $sqlTableExists = 'SHOW TABLES LIKE ' . $this->dbConfig->quote($tableRelation);
            $statement = $this->dbConfig->query($sqlTableExists);
            $tableExists = $statement && $statement->rowCount() > 0;

            return $tableExists ? round((float) $this->sqlFetchValue($sqlAverage), 1) : null;
        };

        $data['total'] = (int) $this->sqlFetchValue('SELECT COUNT(id) FROM notification WHERE is_activated=1');
        $data['avg_hg_notification'] = $avgGetValue('notification_hg_relation');
        $data['avg_sg_notification'] = $avgGetValue('notification_sg_relation');
        $data['avg_bv_notification'] = $avgGetValue('mod_bam_notification_bv_relation');
        $data['avg_contact_notification'] = $avgGetValue('notification_user_relation');
        $data['avg_cg_notification'] = $avgGetValue('notification_contactgroup_relation');

        return array_filter($data, static fn(mixed $value): bool => null !== $value);
    }

    /**
     * @return array<string,array<string, array{widget_number: int, metric_number?: int}>>
     * @throws PDOException
     */
    private function getAdditionalDashboardsInformation(): array
    {
        $data = [];
        $dashboardsInformations = $this->dbConfig->query(
            <<<'SQL'
                SELECT `dashboard_id`,
                    `name` AS `widget_name`,
                    `widget_settings`
                FROM
                    `dashboard_panel`
                SQL
        );

        $extractMetricInformationFromWidgetSettings = function (array $widgetSettings): int|null
        {
            return array_key_exists('metrics', $widgetSettings['data'])
                ? count($widgetSettings['data']['metrics'])
                : null;
        };

        $dashboardId = '';
        foreach ($dashboardsInformations as $dashboardsInformation) {
            $widgetName = (string) $dashboardsInformation['widget_name'];
            $widgetSettings = json_decode((string) $dashboardsInformation['widget_settings'], true);

            if ($dashboardId !== (string) $dashboardsInformation['dashboard_id']) {
                $dashboardId = (string) $dashboardsInformation['dashboard_id'];
                $data[$dashboardId] = [];
            }

            if (array_key_exists($widgetName, $data[$dashboardId])) {
                $data[$dashboardId][$widgetName]['widget_number'] += 1;

                if ($data[$dashboardId][$widgetName]['metric_number'] !== null) {
                    $data[$dashboardId][$widgetName]['metric_number'] += $extractMetricInformationFromWidgetSettings($widgetSettings);
                }
            } else {
                $data[$dashboardId][$widgetName]['widget_number'] = 1;
                $data[$dashboardId][$widgetName]['metric_number'] = $extractMetricInformationFromWidgetSettings($widgetSettings);
            }
        }

        return $data;
    }

    /**
     * @return array<string,array{rotation_time: int, dashboards_count: int, shared_users_groups_count: int}>
     * @throws PDOException
     */
    private function getAdditionalDashboardPlaylistsInformation(): array
    {
        $data = [];
        $statement = $this->dbConfig->query(
            <<<SQL
                SELECT
                    dp.name,
                    dp.rotation_time,
                    COALESCE(viewer_contacts.viewer_count, 0) AS viewer_contacts,
                    COALESCE(editor_contacts.editor_count, 0) AS editor_contacts,
                    COALESCE(viewer_contactgroups.viewer_group_count, 0) AS viewer_contactgroups,
                    COALESCE(editor_contactgroups.editor_group_count, 0) AS editor_contactgroups,
                    COALESCE(dashboard_rels.dashboard_count, 0) AS dashboard_count
                FROM
                    dashboard_playlist dp
                LEFT JOIN (
                    SELECT playlist_id, COUNT(*) AS viewer_count
                    FROM dashboard_playlist_contact_relation
                    WHERE role = 'viewer'
                    GROUP BY playlist_id
                ) AS viewer_contacts ON dp.id = viewer_contacts.playlist_id
                LEFT JOIN (
                    SELECT playlist_id, COUNT(*) AS editor_count
                    FROM dashboard_playlist_contact_relation
                    WHERE role = 'editor'
                    GROUP BY playlist_id
                ) AS editor_contacts ON dp.id = editor_contacts.playlist_id
                LEFT JOIN (
                    SELECT playlist_id, COUNT(*) AS viewer_group_count
                    FROM dashboard_playlist_contactgroup_relation
                    WHERE role = 'viewer'
                    GROUP BY playlist_id
                ) AS viewer_contactgroups ON dp.id = viewer_contactgroups.playlist_id
                LEFT JOIN (
                    SELECT playlist_id, COUNT(*) AS editor_group_count
                    FROM dashboard_playlist_contactgroup_relation
                    WHERE role = 'editor'
                    GROUP BY playlist_id
                ) AS editor_contactgroups ON dp.id = editor_contactgroups.playlist_id
                LEFT JOIN (
                    SELECT playlist_id, COUNT(*) AS dashboard_count
                    FROM dashboard_playlist_relation
                    GROUP BY playlist_id
                ) AS dashboard_rels ON dp.id = dashboard_rels.playlist_id
            SQL
        );

        while (false !== ($record = $statement->fetch(PDO::FETCH_ASSOC))) {
            $data[$record['name']] = [
                'rotation_time' => $record['rotation_time'],
                'dashboards_count' => $record['dashboard_count'],
                'shared_viewer_contacts' => $record['viewer_contacts'],
                'shared_editor_contacts' => $record['editor_contacts'],
                'shared_viewer_contactgroups' => $record['viewer_contactgroups'],
                'shared_editor_contactgroups' => $record['editor_contactgroups'],
            ];
        }

        return $data;
    }

    /**
     * @return array{
     *     nb_users: int,
     *     avg_filters_per_user: float,
     *     max_filters_user: int
     * }
     * @throws PDOException
     */
    private function getAdditionalUserFiltersInformation(): array
    {
        $data = [];

        $filtersPerUserRequest = <<<SQL
            SELECT COUNT(id) as count FROM user_filter GROUP BY user_id;
        SQL;

        $statement = $this->dbConfig->query($filtersPerUserRequest);

        $filtersPerUser = [];

        while (false !== ($record = $statement->fetch(PDO::FETCH_ASSOC))) {
            $filtersPerUser[] = $record['count'];
        }

        $data['nb_users'] = (int) $this->sqlFetchValue('SELECT COUNT(DISTINCT user_id) FROM user_filter');
        $filters = (int) $this->sqlFetchValue('SELECT COUNT(id) FROM user_filter');
        $data['avg_filters_per_user'] = ((int) $data['nb_users'] !== 0) ? (float) ($filters / $data['nb_users']) : 0;
        $data['max_filters_user'] = ($filtersPerUser !== []) ? (int) max(array_values($filtersPerUser)) : 0;

        return $data;
    }

    /**
     * @param string $providerType
     *
     * @return int
     */
    private function getAclRelationsByProviderType(string $providerType): int
    {
        return (int) $this->sqlFetchValue(
            <<<'SQL'
                SELECT COUNT(*) AS acl_relation
                FROM security_provider_access_group_relation gr
                INNER JOIN provider_configuration pc on pc.id = gr.provider_configuration_id
                WHERE pc.type = :providerType
                SQL,
            [':providerType', $providerType, PDO::PARAM_STR]
        );
    }

    /**
     * @param string $providerType
     *
     * @return int
     */
    private function getContactGroupRelationsByProviderType(string $providerType): int
    {
        return (int) $this->sqlFetchValue(
            <<<'SQL'
                SELECT COUNT(*) AS cg_relation
                FROM security_provider_contact_group_relation cg
                INNER JOIN provider_configuration pc on pc.id = cg.provider_configuration_id
                WHERE pc.type = :providerType
                SQL,
            [':providerType', $providerType, PDO::PARAM_STR]
        );
    }

    /**
     * Helper to retrieve the first value of a SQL query.
     *
     * @param string $sql
     * @param array{string, mixed, int} ...$binds List of [':field', $value, \PDO::PARAM_STR]
     *
     * @return string|float|int|null
     */
    private function sqlFetchValue(string $sql, array ...$binds): string|float|int|null
    {
        try {
            $statement = $this->dbConfig->prepare($sql) ?: null;
            foreach ($binds as $args) {
                $statement?->bindValue(...$args);
            }
            $statement?->execute();
            $row = $statement?->fetch(PDO::FETCH_NUM);
            $value = is_array($row) && isset($row[0]) ? $row[0] : null;

            return is_string($value) || is_int($value) || is_float($value) ? $value : null;
        } catch (PDOException $exception) {
            $this->logger->error($exception->getMessage(), ['context' => $exception]);

            return null;
        }
    }
}
