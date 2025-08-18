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

session_start();
require_once __DIR__ . '/../../../../bootstrap.php';
require_once __DIR__ . '/../../../include/common/vault-functions.php';

use App\Kernel;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Infrastructure\FeatureFlags;
use Symfony\Component\Dotenv\Dotenv;

$step = new CentreonLegacy\Core\Install\Step\Step9($dependencyInjector);
$version = $step->getVersion();

$parameters = filter_input_array(INPUT_POST);
if ((int) $parameters['send_statistics'] == 1) {
    $query = "INSERT INTO options (`key`, `value`) VALUES ('send_statistics', '1')";
} else {
    $query = "INSERT INTO options (`key`, `value`) VALUES ('send_statistics', '0')";
}

$db = $dependencyInjector['configuration_db'];
$db->query("DELETE FROM options WHERE `key` = 'send_statistics'");
$db->query($query);

$message = '';
try {
    // Handle the migration of the database credentials to Vault.
    (new Dotenv())->bootEnv('/usr/share/centreon/.env');
    $isCloudPlatform = false;
    if (array_key_exists('IS_CLOUD_PLATFORM', $_ENV) && $_ENV['IS_CLOUD_PLATFORM']) {
        $isCloudPlatform = true;
    }
    $featuresFileContent = file_get_contents(__DIR__ . '/../../../../config/features.json');
    $featureFlagManager = new FeatureFlags($isCloudPlatform, $featuresFileContent);
    $isVaultFeatureEnable = $featureFlagManager->isEnabled('vault');
    if ($isVaultFeatureEnable && file_exists(_CENTREON_VARLIB_ . '/vault/vault.json')) {
        $kernel = Kernel::createForWeb();
        $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);
        $writeVaultRepository->setCustomPath('database');
        $databaseVaultPaths = migrateDatabaseCredentialsToVault($writeVaultRepository);
        if ($databaseVaultPaths !== []) {
            updateConfigFilesWithVaultPath($databaseVaultPaths);
        }
        if ($featureFlagManager->isEnabled('vault_gorgone')) {
            $gorgoneVaultPaths = migrateGorgoneCredentialsToVault($writeVaultRepository);
            if ($gorgoneVaultPaths !== []) {
                updateGorgoneApiFile($gorgoneVaultPaths);
            }
        }
    }

    $backupDir = _CENTREON_VARLIB_ . '/installs/'
        . '/install-' . $version . '-' . date('Ymd_His');
    $installDir = realpath(__DIR__ . '/../..');
    $dependencyInjector['filesystem']->mirror($installDir, $backupDir);
    $dependencyInjector['filesystem']->remove($installDir);
    if ($dependencyInjector['filesystem']->exists($installDir)) {
        throw new Exception(
            'Cannot move directory from ' . $installDir . ' to ' . $backupDir
            . ', please move it manually.'
        );
    }
    $dependencyInjector['filesystem']->remove($backupDir . '/tmp/admin.json');
    $dependencyInjector['filesystem']->remove($backupDir . '/tmp/database.json');

    $result = true;
} catch (Throwable $e) {
    $result = false;
    $message = $e->getMessage();
}

echo json_encode(['result' => $result, 'message' => $message]);
