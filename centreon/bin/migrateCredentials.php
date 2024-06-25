<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/centreon.config.php';
require_once __DIR__ . '/../www/include/common/vault-functions.php';

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

$kernel = \App\Kernel::createForWeb();
$readVaultConfigurationRepository = $kernel->getContainer()->get(
    Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface::class
);
$vaultConfiguration = $readVaultConfigurationRepository->find();
/**
 * @var \Centreon\Domain\Log\Logger $logger
 */
$logger = $kernel->getContainer()->get(\Centreon\Domain\Log\Logger::class);
$httpClient = new CentreonRestHttp();

try {
    echo('Migration of database credentials');
    migrateDatabaseCredentialsToVault($vaultConfiguration, $logger, $httpClient);
    echo('Migration of database credentials done');
} catch (Throwable $e) {
    echo((string) $e);
}

$process = Process::fromShellCommandline(
    'sudo -u apache php /usr/share/centreon/bin/console list '
    . '| grep credentials:migrate-vault'
);
$process->setWorkingDirectory('/usr/share/centreon');
$process->run();

if (! $process->isSuccessful()) {
    throw new ProcessFailedException($process);
}

preg_match_all('/((.*)credentials:migrate-vault)(.*)/', $process->getOutput(), $matches);
$migrationCommands = array_map(fn(string $command): string => trim($command), $matches[1]);

foreach ($migrationCommands as $migrationCommand) {
    $process = Process::fromShellCommandline(
        'sudo -u apache php /usr/share/centreon/bin/console ' . $migrationCommand
    );
    $process->setWorkingDirectory('/usr/share/centreon');
    $process->run();

    if (! $process->isSuccessful()) {
        throw new ProcessFailedException($process);
    }

    echo ($process->getOutput() . PHP_EOL);
}