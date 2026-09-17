<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace CentreonRemote\Domain\Exporter;

use CentreonRemote\Infrastructure\Export\ExportManifest;
use CentreonRemote\Infrastructure\Service\ExporterServiceAbstract;
use ConfigGenerateRemote\Manifest;

class ConfigurationExporter extends ExporterServiceAbstract
{
    public const NAME = 'configuration';
    private const MEDIA_PATH = _CENTREON_PATH_ . 'www/img/media';

    /** @var \ConfigGenerateRemote\Generate */
    private $generateService;

    /**
     * Set generate service.
     *
     * @param \ConfigGenerateRemote\Generate $generateService
     */
    public function setGenerateService(\ConfigGenerateRemote\Generate $generateService): void
    {
        $this->generateService = $generateService;
    }

    /**
     * Export data.
     *
     * @param int $remoteId
     *
     * @return mixed[]
     */
    public function export(int $remoteId): array
    {
        // create path
        $this->createPath();

        $this->generateService->configRemoteServerFromId($remoteId, 'user');

        return Manifest::getInstance($this->dependencyInjector)->getManifest();
    }

    /**
     * Import data.
     *
     * @param ExportManifest $manifest
     */
    public function import(ExportManifest $manifest): void
    {
        // skip if no data
        if (! is_dir($this->getPath())) {
            return;
        }

        $db = $this->db->getAdapter('configuration_db');
        $connection = $db->getCentreonDBInstance();

        // get tables
        $tables = array_fill_keys($connection->fetchFirstColumn('SHOW TABLES'), 1);

        $import = $manifest->get('import');

        // Phase 1: clear tables and reset auto_increment outside transaction.
        // ALTER TABLE (DDL) causes an implicit commit in MySQL/MariaDB, so it must
        // run before startTransaction() to avoid breaking the import transaction.
        // Disable FK checks to prevent CASCADE deletes on tables outside the export manifest.
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $truncated = [];
            foreach ($import['data'] as $data) {
                if (! isset($truncated[$data['table']]) && isset($tables[$data['table']])) {
                    $table = $this->assertSafeTableName($data['table']);
                    $connection->executeStatement('DELETE FROM `' . $table . '`');
                    $connection->executeStatement('ALTER TABLE `' . $table . '` AUTO_INCREMENT = 1');
                    $truncated[$data['table']] = 1;
                }
            }
        } finally {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Phase 2: import data inside a transaction.
        $connection->startTransaction();

        try {
            // allow insert records without foreign key checks
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($import['data'] as $data) {
                $exportPathFile = $this->getFile($data['filename']);
                $size = filesize($exportPathFile);
                echo date('Y-m-d H:i:s') . " - INFO - Loading '" . $exportPathFile . "' ({$size}).\n";

                if ($size > 0 && ! isset($tables[$data['table']])) {
                    echo date('Y-m-d H:i:s') . " - ERROR - cannot import table '" . $data['table'] . "': not exist.\n";
                    continue;
                }

                // insert data
                if ($size > 0) {
                    $db->loadDataInfile(
                        $exportPathFile,
                        $this->assertSafeTableName($data['table']),
                        $import['infile_clauses']['fields_clause'],
                        $import['infile_clauses']['lines_clause'],
                        $data['columns']
                    );
                }
            }

            if ($connection->isTransactionActive()) {
                // commit transaction
                $connection->commitTransaction();
            }
        } catch (\Throwable $e) {
            // rollback changes — broad catch so a failed assertSafeTableName()/
            // loadDataInfile()/PDO error also triggers an explicit rollback
            if ($connection->isTransactionActive()) {
                $connection->rollBackTransaction();
            }
            echo date('Y-m-d H:i:s') . " - ERROR - Loading failed.\n";

            throw $e;
        } finally {
            // restore foreign key checks
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }

        // media copy
        $exportPathMedia = $this->commitment->getPath() . '/media';
        $mediaPath = self::MEDIA_PATH;
        $this->recursiveCopy($exportPathMedia, $mediaPath);
    }

    public static function order(): int
    {
        return 40;
    }

    /**
     * @return string
     */
    public static function getName(): string
    {
        return static::NAME;
    }

    /**
     * Defence-in-depth on top of the SHOW TABLES allowlist: ensures a table
     * name is a safe SQL identifier before it is concatenated into a query.
     *
     * @throws \InvalidArgumentException
     */
    private function assertSafeTableName(mixed $table): string
    {
        if (! is_string($table) || preg_match('/^[a-zA-Z0-9_]+$/', $table) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'Unsafe table name in import manifest: %s',
                is_scalar($table) ? (string) $table : get_debug_type($table),
            ));
        }

        return $table;
    }

    /**
     * Copy directory recursively.
     *
     * @param string $src
     * @param string $dst
     */
    private function recursiveCopy($src, $dst): void
    {
        $dir = opendir($src);
        @mkdir($dst, $this->commitment->getFilePermission(), true);
        while (($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->recursiveCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    echo date('Y-m-d H:i:s') . " - INFO - Copying '" . $src . '/' . $file . "'.\n";
                    copy($src . '/' . $file, $dst . '/' . $file);
                    chmod($dst . '/' . $file, $this->commitment->getFilePermission());
                }
            }
        }
        closedir($dir);
    }
}
