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

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/centreon.config.php';
require_once __DIR__ . '/../www/include/common/vault-functions.php';

use App\Kernel;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Infrastructure\FeatureFlags;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

try {
    if (posix_getuid() !== 0) {
        throw new Exception('This script must be run as root');
    }
    $kernel = Kernel::createForWeb();
    $readVaultConfigurationRepository = $kernel->getContainer()->get(
        ReadVaultConfigurationRepositoryInterface::class
    );
    $vaultConfiguration = $readVaultConfigurationRepository->find();

    if ($vaultConfiguration === null) {
        throw new Exception('No vault configured');
    }

    /** @var ReadVaultRepositoryInterface $readVaultRepository */
    $readVaultRepository = $kernel->getContainer()->get(ReadVaultRepositoryInterface::class);
    /** @var WriteVaultRepositoryInterface $writeVaultRepository */
    $writeVaultRepository = $kernel->getContainer()->get(WriteVaultRepositoryInterface::class);

    revertAndUpdateDatabaseCredentials($readVaultRepository, $writeVaultRepository);
    revertGorgoneApiCredentials($readVaultRepository, $writeVaultRepository);
    revertApplicationCredentials();

} catch (Throwable $ex) {
    echo $ex->getMessage() . PHP_EOL;
}

/**
 * Revert Gorgone API credentials from the vault back to the configuration file.
 *
 * This is handle outside of Symfony Command as this should be executed as root.
 *
 * @param ReadVaultRepositoryInterface $readVaultRepository
 * @param WriteVaultRepositoryInterface $writeVaultRepository
 *
 * @throws Throwable
 */
function revertGorgoneApiCredentials(
    ReadVaultRepositoryInterface $readVaultRepository,
    WriteVaultRepositoryInterface $writeVaultRepository,
): void {
    echo 'Revert of Gorgone API credentials' . PHP_EOL;

    (new Dotenv())->bootEnv('/usr/share/centreon/.env');
    $isCloudPlatform = false;
    if (array_key_exists('IS_CLOUD_PLATFORM', $_ENV) && $_ENV['IS_CLOUD_PLATFORM']) {
        $isCloudPlatform = true;
    }
    $featuresFileContent = file_get_contents(__DIR__ . '/../config/features.json');
    $featureFlagManager = new FeatureFlags($isCloudPlatform, $featuresFileContent);
    if ($featureFlagManager->isEnabled('vault_gorgone')) {
        revertGorgoneCredentialsToDb($readVaultRepository, $writeVaultRepository);
    }

    echo 'Revert of Gorgone API credentials completed' . PHP_EOL;
}

/**
 * Execute Symfony command to revert web and modules credentials.
 *
 * @throws ProcessFailedException
 */
function revertApplicationCredentials(): void
{
    echo 'Revert of application credentials' . PHP_EOL;
    $process = Process::fromShellCommandline(
        'sudo -u apache php ' . _CENTREON_PATH_ . '/bin/console list vault:revert-credentials'
    );
    $process->setWorkingDirectory(_CENTREON_PATH_);
    $process->run();

    if (! $process->isSuccessful()) {
        echo 'No application revert command available, skipping' . PHP_EOL;

        return;
    }

    preg_match_all('/\S*vault:revert-credentials:\S*/', $process->getOutput(), $matches);
    foreach ($matches[0] as $revertCommand) {
        $process = Process::fromShellCommandline(
            'sudo -u apache php ' . _CENTREON_PATH_ . '/bin/console ' . $revertCommand
        );
        $process->setWorkingDirectory(_CENTREON_PATH_);
        $process->mustRun(function ($type, $buffer): void {
            if ($type === Process::ERR) {
                echo 'ERROR: ' . $buffer . PHP_EOL;
            } else {
                echo $buffer;
            }
        });
    }
    echo 'Revert of application credentials completed' . PHP_EOL;
}
