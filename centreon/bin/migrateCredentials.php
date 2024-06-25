<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

if (posix_getuid() !== 0) {
    throw new \RuntimeException('This script is only available using root user.');
}

$process = Process::fromShellCommandline('sudo -u apache php /usr/share/centreon/bin/console list | grep credentials:migrate-vault');
$process->setWorkingDirectory('/usr/share/centreon');
$process->run();
if (!$process->isSuccessful()) {
    throw new ProcessFailedException($process);
}
preg_match_all('/((.*)credentials:migrate-vault)(.*)/', $process->getOutput(), $matches);
foreach ($matches as $match) {
    var_dump(trim($match[1]));
}
