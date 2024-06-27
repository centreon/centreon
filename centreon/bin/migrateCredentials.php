<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/centreon.config.php';
require_once __DIR__ . '/../www/include/common/vault-functions.php';

use App\Kernel;
use Centreon\Domain\Log\Logger;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

try {
    if (posix_getuid() !== 0) {
        throw new Exception('This script must be run as root');
    }

    migrateAndUpdateDatabaseCredentials();
    migrateApplicationCredentials();

} catch (Throwable $ex) {
    echo($ex->getMessage() . PHP_EOL);
}

/**
 * Migrate database credentials into the vault and update configuration files.
 *
 * This is handle outside of Symfony Command as this should be executed as root.
 *
 * @throws Throwable
 */
function migrateAndUpdateDatabaseCredentials(): void {
    $kernel = Kernel::createForWeb();
    $readVaultConfigurationRepository = $kernel->getContainer()->get(
        ReadVaultConfigurationRepositoryInterface::class
    );
    $vaultConfiguration = $readVaultConfigurationRepository->find();

    if ($vaultConfiguration === null) {
        throw new Exception('No vault configured');
    }

    /**
     * @var Logger $logger
     */
    $logger = $kernel->getContainer()->get(Logger::class);
    $httpClient = new CentreonRestHttp();
    echo('Migration of database credentials' . PHP_EOL);
    $vaultPath = migrateDatabaseCredentialsToVault($vaultConfiguration, $logger, $httpClient);
    updateConfigFilesWithVaultPath($vaultPath);
    echo('Migration of database credentials completed' . PHP_EOL);
}

/**
 * Execute Symfony command to migrate web and modules credentials.
 *
 * @throws ProcessFailedException
 */
function migrateApplicationCredentials(): void
{
    echo('Migration of application credentials' . PHP_EOL);
    $process = Process::fromShellCommandline(
        'sudo -u apache php ' . _CENTREON_PATH_ . '/bin/console list vault:migrate-credentials'
    );
    $process->setWorkingDirectory(_CENTREON_PATH_);
    $process->mustRun();

    preg_match_all('/\S*vault:migrate-credentials:\S*/', $process->getOutput(), $matches);
    foreach ($matches[0] as $migrationCommand) {
        $process = Process::fromShellCommandline(
            'sudo -u apache php ' . _CENTREON_PATH_ . '/bin/console ' . $migrationCommand
        );
        $process->setWorkingDirectory(_CENTREON_PATH_);
        $process->mustRun();
        if (! empty($process->getOutput())) {
            echo ($process->getOutput() . PHP_EOL);
        }
    }
    echo('Migration of application credentials completed' . PHP_EOL);
}