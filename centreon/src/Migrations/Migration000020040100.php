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

namespace Migrations;

require_once __DIR__  . '/../../www/class/centreonLog.class.php';

use Centreon\Domain\Log\LoggerTrait;
use Core\Migration\Application\Repository\LegacyMigrationInterface;
use Core\Migration\Infrastructure\Repository\AbstractCoreMigration;
use Pimple\Container;

class Migration000020040100 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '20.04.1';

    public function __construct(
        private readonly Container $dependencyInjector,
        private string $dbHost,
        private string $dbPort,
        private string $dbUser,
        private string $dbPassword,
        private string $centreonDbName,
        private string $storageDbName,
        private string $centreonVarLib,
        private string $centreonCacheDir,
        private string $centreonEtcPath,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return sprintf(_('Update to %s'), self::VERSION);
    }

    /**
     * {@inheritDoc}
     */
    public function up(): void
    {
        $pearDB = $this->dependencyInjector['configuration_db'];

        // Update-20.04.1.php

        $centreonLog = new \CentreonLog();

        // error specific content
        $versionOfTheUpgrade = 'UPGRADE - 20.04.1 : ';
        $errorMessage = '';

        /**
         * Queries needing exception management BUT no rollback if failing.
         */
        try {
            // Get user data to generate a new config file for the gorgone daemon module

            // get engine command
            $res = $pearDB->query(
                "SELECT command_file FROM cfg_nagios cn
                JOIN nagios_server ns ON ns.id = cn.nagios_id
                WHERE localhost = '1'"
            );
            $engineCommand = $res->fetch()['command_file'];

            // escape double quotes and backslashes
            $needle = ['\\', '"'];
            $escape = ['\\\\', '\"'];
            $password = str_replace($needle, $escape, $this->dbPassword);

            // set macro keys
            $pattern = [
                '/--ADDRESS--/',
                '/--DBPORT--/',
                '/--DBUSER--/',
                '/--DBPASS--/',
                '/--CONFDB--/',
                '/--STORAGEDB--/',
                '/--CENTREON_VARLIB--/',
                '/--CENTREON_CACHEDIR--/',
                '/--CENTREON_SPOOL--/',
                '/--CENTREON_TRAPDIR--/',
                '/--HTTPSERVERADDRESS--/',
                '/--HTTPSERVERPORT--/',
                '/--SSLMODE--/',
                '/--GORGONE_VARLIB--/',
                '/--ENGINE_COMMAND--/',
            ];

            // set default values for these parameters
            $userValues = [
                $this->dbHost,
                $this->dbPort,
                $this->dbUser,
                $password,
                $this->centreonDbName,
                $this->storageDbName,
                $this->centreonVarLib,
                $this->centreonCacheDir,
                '/var/spool/centreon',
                '/etc/snmp/centreon_traps',
                '0.0.0.0',
                '8085',
                'false',
                '/var/lib/centreon-gorgone',
                $engineCommand,
            ];

            /**
             * check if the file has already been generated on a 20.04.0-beta or not
             * if already exists, generate a new file.
             *
             * @param string $destinationFile path to the file
             *
             * @return string corrected filename
             */
            $returnFinalFileName = function (string $destinationFile)
            {
                if (file_exists($destinationFile)) {
                    $destinationFile .= '.new';
                }

                return $destinationFile;
            };

            // database configuration file
            $fileTpl = __DIR__ . '/../../www/install/var/databaseTemplate.yaml';
            if (! file_exists($fileTpl) || 0 === filesize($fileTpl)) {
                $errorMessage = 'Database configuration template is empty or missing';

                throw new \InvalidArgumentException($errorMessage);
            }
            $content = file_get_contents($fileTpl);
            $content = preg_replace($pattern, $userValues, $content);
            $destinationFile = $returnFinalFileName($this->centreonEtcPath . '/config.d/10-database.yaml');
            file_put_contents($destinationFile, $content);

            if (! file_exists($destinationFile) || 0 === filesize($destinationFile)) {
                $errorMessage = 'Database configuration file is not created properly';

                throw new \InvalidArgumentException($errorMessage);
            }

            // gorgone configuration file for centreon. Created in the centreon-gorgone folder
            $fileTpl = __DIR__ . '/../../www/install/var/gorgone/gorgoneCentralTemplate.yaml';
            if (! file_exists($fileTpl) || 0 === filesize($fileTpl)) {
                $errorMessage = 'Gorgone configuration template is empty or missing';

                throw new \InvalidArgumentException($errorMessage);
            }
            $content = file_get_contents($fileTpl);
            $content = preg_replace($pattern, $userValues, $content);
            $destinationFolder = $this->centreonEtcPath . '/../centreon-gorgone';
            $destinationFile = $returnFinalFileName($destinationFolder . '/config.d/40-gorgoned.yaml');

            // checking if mandatory centreon-gorgone configuration sub-folder already exists
            if (! file_exists($destinationFolder . '/config.d')) {
                $errorMessage = 'Gorgone configuration folder does not exist. '
                    . 'Please reinstall the centreon-gorgone package and retry';

                throw new \InvalidArgumentException($errorMessage);
            }
            file_put_contents($destinationFile, $content);

            if (! file_exists($destinationFile) || 0 === filesize($destinationFile)) {
                $errorMessage = 'Gorgone configuration file is not created properly';

                throw new \InvalidArgumentException($errorMessage);
            }
        } catch (\Exception $e) {
            $centreonLog->insertLog(
                4,
                $versionOfTheUpgrade . $errorMessage
                . ' - Code : ' . $e->getCode()
                . ' - Error : ' . $e->getMessage()
                . ' - Trace : ' . $e->getTraceAsString()
            );

            throw new \Exception($versionOfTheUpgrade . $errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function down(): void
    {
        // nothing
    }
}
