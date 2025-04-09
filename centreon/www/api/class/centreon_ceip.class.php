<?php

/*
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
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

use CentreonLicense\ServiceProvider;

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../class/centreonDB.class.php';
require_once __DIR__ . '/../../class/centreonUUID.class.php';
require_once __DIR__ . '/../../class/centreonStatistics.class.php';
require_once __DIR__ . '/webService.class.php';

class CentreonCeip extends CentreonWebService
{
    /**
     * @var string
     */
    private $uuid;

    /**
     * @var \CentreonUser
     */
    private $user;

    /**
     * @var \Centreon\Domain\Log\Logger
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        global $centreon;

        $this->user = $centreon->user;

        // Generate UUID
        $this->uuid = (new CentreonUUID($this->pearDB))->getUUID();

        $kernel = \App\Kernel::createForWeb();
        $this->logger = $kernel->getContainer()->get(
            \Centreon\Domain\Log\Logger::class
        );
    }

    /**
     * Get CEIP Account and User info
     *
     * @return array<string,mixed> with Account/User info
     */
    public function getCeipInfo(): array
    {
        // Don't compute data is CEIP is disabled
        if (!$this->isCeipActive()) {
            return [
                'ceip' => false
            ];
        }

        return [
            'visitor' => $this->getVisitorInformation(),
            'account' => $this->getAccountInformation(),
            'excludeAllText' => true,
            'ceip' => true
        ];
    }

    /**
     * Get the type of the Centreon server
     *
     * @return array the type of the server (central|remote and on_premise|centreon_cloud)
     */
    private function getServerType(): array
    {
        // Default parameters
        $instanceInformation = [
            'type' => 'central',
            'platform' => 'on_premise'
        ];

        $result = $this->pearDB->query(
            "SELECT * FROM `informations` WHERE `key` IN ('isRemote', 'is_cloud')"
        );
        while ($row = $result->fetch()) {
            if ($row['key'] === 'is_cloud' && $row['value'] === 'yes') {
                $instanceInformation['platform'] = 'centreon_cloud';
            }
            if ($row['key'] === 'isRemote' && $row['value'] === 'yes') {
                $instanceInformation['type'] = 'remote';
            }
        }

        return $instanceInformation;
    }

    /**
     * Get visitor information
     *
     * @return array<string,mixed> with visitor information
     * @throws \PDOException
     */
    private function getVisitorInformation(): array
    {
        $locale = $this->user->get_lang();

        $role = $this->user->admin
            ? "admin"
            : "user";

        if (strcmp($role, 'admin') != 0) {
            $stmt = $this->pearDB->prepare('
                SELECT COUNT(*) AS countAcl
                FROM acl_actions_rules AS aar
                INNER JOIN acl_actions AS aa ON (aa.acl_action_id = aar.acl_action_rule_id)
                INNER JOIN acl_group_actions_relations AS agar ON (agar.acl_action_id = aar.acl_action_rule_id)
                INNER JOIN acl_group_contacts_relations AS agcr ON (agcr.acl_group_id = agar.acl_group_id)
                INNER JOIN acl_group_contactgroups_relations AS agcgr ON (agcgr.acl_group_id = agar.acl_group_id)
                WHERE aar.acl_action_name LIKE "service\_%" OR aar.acl_action_name LIKE "host\_%"
                AND agcr.contact_contact_id = :contact_id
                OR agcgr.acl_group_id IN (
                    SELECT contactgroup_cg_id
                    FROM contactgroup_contact_relation
                    WHERE contact_contact_id = :contact_id
                )
            ');
            $stmt->bindValue(':contact_id', $this->user->user_id, PDO::PARAM_INT);
            $stmt->execute();
            if (($row = $stmt->fetch()) && $row['countAcl'] > 0) {
                $role = 'operator';
            }
        }

        return [
            'id' => substr($this->uuid, 0, 6) . '-' . $this->user->user_id,
            'locale' => $locale,
            'role' => $role
        ];
    }

    /**
     * Get account information
     *
     * @return array<string,mixed> with account information
     */
    private function getAccountInformation(): array
    {
        // Get Centreon statistics
        $centreonStats = new CentreonStatistics($this->logger);
        $configUsage = $centreonStats->getPlatformInfo();

        // Get Licences information
        $licenseInfo = $this->getLicenseInformation();

        // Get Version of Centreon
        $centreonVersion = $this->getCentreonVersion();

        // Get Instance information
        $instanceInformation = $this->getServerType();

        // Get LACCESS
        $laccess = $this->getLaccess();

        $accountInformation =  [
            'id' => $this->uuid,
            'name' => $licenseInfo['companyName'],
            'serverType' => $instanceInformation['type'],
            'platformType' => $instanceInformation['platform'],
            'platformEnvironment' => $licenseInfo['platformEnvironment'],
            'licenseType' => $licenseInfo['licenseType'],
            'versionMajor' => $centreonVersion['major'],
            'versionMinor' => $centreonVersion['minor'],
            'nb_hosts' => (int) $configUsage['nb_hosts'],
            'nb_services' => (int) $configUsage['nb_services'],
            'nb_servers' => $configUsage['nb_central'] + $configUsage['nb_remotes'] + $configUsage['nb_pollers']
        ];

        if (isset($licenseInfo['hosts_limitation'])) {
            $accountInformation['hosts_limitation'] = $licenseInfo['hosts_limitation'];
        }
        if (isset($licenseInfo['fingerprint'])) {
            $accountInformation['fingerprint'] = $licenseInfo['fingerprint'];
        }

        if (!empty($laccess) && isset($licenseInfo['mode']) && $licenseInfo['mode'] !== 'offline') {
            $accountInformation['LACCESS'] = $laccess;
        }

        return $accountInformation;
    }

    /**
     * Get license information such as company name and license type
     *
     * @return array<string,string> with license info
     */
    private function getLicenseInformation(): array
    {
        /**
         * Getting License informations.
         */
        $dependencyInjector = \Centreon\LegacyContainer::getInstance();
        $fingerprintService = $dependencyInjector[ServiceProvider::LM_FINGERPRINT];

        $productLicense = 'Open Source';
        $licenseClientName = '';
        try {
            $centreonModules = ['epp', 'bam', 'map', 'mbi'];
            $licenseObject = $dependencyInjector['lm.license'];
            $licenseInformation = [];
            foreach ($centreonModules as $module) {
                $licenseObject->setProduct($module);
                $isLicenseValid = $licenseObject->validate(false);
                if ($isLicenseValid && !empty($licenseObject->getData())) {
                    $licenseInformation[$module] = $licenseObject->getData();
                    $licenseClientName = $licenseInformation[$module]['client']['name'];
                    $hostsLimitation = $licenseInformation[$module]['licensing']['hosts'];
                    $licenseMode = $licenseInformation[$module]['platform']['mode'] ?? null;
                    $licenseStart = DateTime::createFromFormat(
                        'Y-m-d',
                        $licenseInformation[$module]['licensing']['start']
                    ) ?: throw new Exception('Invalid date format');
                    $licenseEnd = DateTime::createFromFormat(
                        'Y-m-d',
                        $licenseInformation[$module]['licensing']['end']
                    ) ?: throw new Exception('Invalid date format');
                    $licenseDurationInMonths = $licenseEnd->diff($licenseStart)->m;
                    if ($module === 'epp') {
                        $productLicense = 'IT Edition';
                        if ($licenseInformation[$module]['licensing']['type'] === 'IT100') {
                            $productLicense = 'IT-100 Edition';
                        } else if ($hostsLimitation === -1 && $licenseDurationInMonths > 3) {
                            $productLicense = 'MSP Edition';
                            $fingerprint = $fingerprintService->calculateFingerprint();
                        }
                    }
                    if (in_array($module, ['mbi', 'bam', 'map'])) {
                        $productLicense = 'Business Edition';
                        $fingerprint = $fingerprintService->calculateFingerprint();
                        if ($hostsLimitation === -1 && $licenseDurationInMonths > 3) {
                            $productLicense = 'MSP Edition';
                        }
                        break;
                    }
                    $environment = $licenseInformation[$module]['platform']['environment'];
                }
            }
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), ['context' => $exception]);
        }

        $licenseInformation = [
            'companyName' => $licenseClientName,
            'licenseType' => $productLicense,
            'platformEnvironment' => $environment ?? 'demo',
        ];

        if (isset($hostsLimitation)) {
            $licenseInformation['hosts_limitation'] = $hostsLimitation;
        }

        if (isset($fingerprint)) {
            $licenseInformation['fingerprint'] = $fingerprint;
        }

        if (isset($licenseMode)) {
            $licenseInformation['mode'] = $licenseMode;
        }

        return $licenseInformation;
    }

    /**
     * Get the major and minor versions of Centreon web
     *
     * @return array<string,string|null> with major and minor versions
     * @throws \PDOException
     */
    private function getCentreonVersion(): array
    {
        $major = null;
        $minor = null;

        $result = $this->pearDB->query(
            "SELECT informations.value FROM informations WHERE informations.key = 'version'"
        );
        if ($row = $result->fetch()) {
            $minor = $row['value'];
            $major = substr($minor, 0, strrpos($minor, '.', 0));
        }

        return [
            'major' => $major,
            'minor' => $minor
        ];
    }

    /**
     * Get CEIP status
     *
     * @return bool the status of CEIP
     * @throws \PDOException
     */
    private function isCeipActive(): bool
    {
        $result = $this->pearDB->query(
            "SELECT `value` FROM `options` WHERE `key` = 'send_statistics' LIMIT 1"
        );

        if (($sendStatisticsResult = $result->fetch()) && $sendStatisticsResult["value"] === "1") {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Get LACCESS to complete the connection between Pendo and Salesforce.
     *
     * @return string LACCESS value from options table.
     *
     * @throws PDOException
     *
     */
    private function getLaccess(): string
    {
        $sql = "SELECT `value` FROM `options` WHERE `key` = 'impCompanyToken' LIMIT 1";
        $impCompanyToken = (string) $this->sqlFetchValue($sql);

        $decodedToken = json_decode($impCompanyToken, true);
        if (is_array($decodedToken) && isset($decodedToken['token'])) {
            return $decodedToken['token'];
        }

        $this->logger->error(
            "Invalid JSON format in options table for key 'impCompanyToken'",
            ['context' => $impCompanyToken]
        );

        return '';
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
            $statement = $this->pearDB?->prepare($sql) ?: null;
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
