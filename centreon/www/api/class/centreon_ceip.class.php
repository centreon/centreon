<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../class/centreonDB.class.php';
require_once __DIR__ . '/../../class/centreonUUID.class.php';
require_once __DIR__ . '/../../class/centreonStatistics.class.php';
require_once __DIR__ . '/../../include/common/common-Func.php';
require_once __DIR__ . '/webService.class.php';

class CentreonCeip extends CentreonWebService
{
    /** @var string */
    private $uuid;

    /** @var \CentreonUser */
    private $user;

    private \Centreon\Domain\Log\Logger $logger;

    private \Core\Common\Infrastructure\FeatureFlags $featureFlags;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        global $centreon;

        $this->user = $centreon->user;

        // Generate UUID
        $this->uuid = (string) (new CentreonUUID($this->pearDB))->getUUID();

        $kernel = \App\Kernel::createForWeb();
        $this->logger = $kernel->getContainer()->get(\Centreon\Domain\Log\Logger::class)
            ?? throw new LogicException('Logger not found in container');
        $this->featureFlags = $kernel->getContainer()->get(\Core\Common\Infrastructure\FeatureFlags::class)
            ?? throw new LogicException('FeatureFlags not found in container');
    }

    /**
     * Get CEIP Account and User info.
     *
     * @return array<string,mixed> with Account/User info
     */
    public function getCeipInfo(): array
    {
        return $this->isCeipActive()
            ? [
                'visitor' => $this->getVisitorInformation(),
                'account' => $this->getAccountInformation(),
                'excludeAllText' => true,
                'ceip' => true,
            ]
            // Don't compute data if CEIP is disabled
            : [
                'ceip' => false,
            ];
    }

    /**
     * Get the type of the Centreon server.
     *
     * @return array{
     *     type: 'central'|'remote',
     *     platform: 'on_premise'|'centreon_cloud',
     * } the type of the server
     */
    private function getServerType(): array
    {
        // Default parameters
        $instanceInformation = [
            'type' => 'central',
            'platform' => 'on_premise',
        ];

        $sql = "SELECT * FROM `informations` WHERE `key` IN ('isRemote', 'is_cloud')";
        $result = $this->pearDB?->query($sql) ?: null;
        while (is_array($row = $result?->fetch())) {
            /** @var array{key:string, value:string} $row */
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
     * Get visitor information.
     *
     * @throws \PDOException
     *
     * @return array<string,mixed> with visitor information
     */
    private function getVisitorInformation(): array
    {
        $locale = $this->user->get_lang();

        
        if (isCloudPlatform()) { 
            // Get the user role for the Centreon Cloud platform

            // Get list of ACL Groups linked to this user
            $grouplistStr = $this->user->access->getAccessGroupsString('NAME');

            // Check main ACL Group
            if (preg_match('/customer_admin_acl/', (string) $grouplistStr)) {
                $role = 'Administrator';
            } elseif (preg_match('/customer_editor_acl/', (string) $grouplistStr)) {
                $role = 'Editor';
            } elseif (preg_match('/customer_user_acl/', (string) $grouplistStr)) {
                $role = 'User';
            } else {
                $role = 'User';
            }
        } else { 
            // Get the user role for the Centreon on-premises platform
            $role = $this->user->admin
                ? 'admin'
                : 'user';

            // If user have access to monitoring configuration, it's an operator
            if (0 !== strcmp($role, 'admin') && $this->user->access->page('601') > 0) {
                $role = 'editor';
            }
        }

        return [
            'id' => mb_substr($this->uuid, 0, 6) . '-' . $this->user->user_id,
            'locale' => $locale,
            'role' => $role,
        ];
    }

    /**
     * Get account information.
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

        return [
            'id' => $this->uuid,
            'name' => $licenseInfo['companyName'],
            'serverType' => $instanceInformation['type'],
            'platformType' => $instanceInformation['platform'],
            'licenseType' => $licenseInfo['licenseType'],
            'versionMajor' => $centreonVersion['major'],
            'versionMinor' => $centreonVersion['minor'],
            'nb_hosts' => (int) $configUsage['nb_hosts'],
            'nb_services' => (int) $configUsage['nb_services'],
            'nb_servers' => $configUsage['nb_central'] + $configUsage['nb_remotes'] + $configUsage['nb_pollers'],
            'enabled_features_tags' => $this->featureFlags->getEnabled() ?: [],
        ];
    }

    /**
     * Get license information such as company name and license type.
     *
     * @return array<string,string> with license info
     */
    private function getLicenseInformation(): array
    {
        /**
         * Getting License information.
         */
        $dependencyInjector = \Centreon\LegacyContainer::getInstance();
        $productLicense = 'Open Source';
        $licenseClientName = '';
        try {
            $centreonModules = ['epp', 'bam', 'map', 'mbi'];

            /** @var \CentreonLicense\Infrastructure\Service\LicenseService $licenseObject */
            $licenseObject = $dependencyInjector['lm.license'];

            /** @var array<array-key, array<array-key, string|array<array-key, string>>> $licenseInformation */
            $licenseInformation = [];
            foreach ($centreonModules as $module) {
                $licenseObject->setProduct($module);
                $isLicenseValid = $licenseObject->validate();

                if ($isLicenseValid && ! empty($licenseObject->getData())) {
                    /**
                     * @var array<
                     *  array-key,
                     *  array<array-key, array<array-key, string|array<array-key, string>>>> $licenseInformation
                     */
                    $licenseInformation[$module] = $licenseObject->getData();
                    /** @var string $licenseClientName */
                    $licenseClientName = $licenseInformation[$module]['client']['name'];

                    if ($module === 'epp') {
                        $productLicense = 'IT Edition';
                        if ($licenseInformation[$module]['licensing']['type'] === 'IT100') {
                            $productLicense = 'IT-100 Edition';
                        }
                    }
                    if (in_array($module, ['mbi', 'bam', 'map'], true)) {
                        $productLicense = 'Business Edition';
                        break;
                    }
                }
            }
        } catch (\Pimple\Exception\UnknownIdentifierException) {
            // The licence does not exist, 99.99% chance we are on Open source. No need to log.
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage(), ['context' => $exception]);
        }

        return [
            'companyName' => $licenseClientName,
            'licenseType' => $productLicense,
        ];
    }

    /**
     * Get the major and minor versions of Centreon web.
     *
     * @throws \PDOException
     *
     * @return array{major: string, minor: string} with major and minor versions
     */
    private function getCentreonVersion(): array
    {
        $sql = "SELECT informations.value FROM informations WHERE informations.key = 'version'";
        $minor = (string) $this->sqlFetchValue($sql);
        $major = mb_substr($minor, 0, (int) mb_strrpos($minor, '.', 0));

        return compact('major', 'minor');
    }

    /**
     * Get CEIP status.
     *
     * @throws \PDOException
     *
     * @return bool the status of CEIP
     */
    private function isCeipActive(): bool
    {
        $sql = "SELECT `value` FROM `options` WHERE `key` = 'send_statistics' LIMIT 1";

        return '1' === $this->sqlFetchValue($sql);
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
            $row = $statement?->fetch(\PDO::FETCH_NUM);
            $value = is_array($row) && isset($row[0]) ? $row[0] : null;

            return is_string($value) || is_int($value) || is_float($value) ? $value : null;
        } catch (\PDOException $exception) {
            $this->logger->error($exception->getMessage(), ['context' => $exception]);

            return null;
        }
    }
}
