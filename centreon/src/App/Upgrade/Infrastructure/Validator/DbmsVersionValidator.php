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

declare(strict_types=1);

namespace App\Upgrade\Infrastructure\Validator;

use App\Upgrade\Application\DbmsVersionValidator as DbmsVersionValidatorPort;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DbmsVersionValidator implements DbmsVersionValidatorPort
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        #[Autowire(param: 'upgrade.min_mariadb_version')]
        private string $minMariaDbVersion,
        #[Autowire(param: 'upgrade.min_mysql_version')]
        private string $minMySqlVersion,
        private LoggerInterface $logger,
    ) {
    }

    public function validateOrFail(): void
    {
        $version = '';
        $versionComment = '';

        $rows = $this->connection->fetchAllAssociative(
            "SHOW VARIABLES WHERE Variable_name IN ('version', 'version_comment')"
        );

        foreach ($rows as $row) {
            /** @var array{Variable_name: string, Value: string} $row */
            if ($row['Variable_name'] === 'version') {
                $version = $row['Value'];
            } elseif ($row['Variable_name'] === 'version_comment') {
                $versionComment = $row['Value'];
            }
        }

        if ($version === '' || $versionComment === '') {
            throw new \RuntimeException('Cannot retrieve the DBMS version information');
        }

        $this->logger->info('DBMS version detected', [
            'version' => $version,
            'version_comment' => $versionComment,
        ]);

        // MariaDB identifies itself either in version_comment or in the version string itself (e.g. "10.5.12-MariaDB").
        $isMariaDb = str_contains($versionComment, 'MariaDB') || str_contains($version, 'MariaDB');

        if ($isMariaDb) {
            $this->assertVersionMeetsMinimum('MariaDB', $version, $this->minMariaDbVersion);
        } else {
            // Treat everything else as MySQL (includes "Source distribution" builds).
            $this->assertVersionMeetsMinimum('MySQL', $version, $this->minMySqlVersion);
        }
    }

    private function assertVersionMeetsMinimum(string $dbms, string $installed, string $required): void
    {
        $installedMajorMinor = implode('.', array_slice(explode('.', $installed), 0, 2));

        if (version_compare($installedMajorMinor, $required, '<')) {
            throw new \RuntimeException(
                sprintf('%s version %s required (%s installed)', $dbms, $required, $installed)
            );
        }

        $this->logger->info(sprintf('%s version requirement met', $dbms), [
            'required' => $required,
            'installed' => $installed,
        ]);
    }
}
