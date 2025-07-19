<?php

namespace Core\Macro\Application;

use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Security\Interfaces\EncryptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

final readonly class MacroExportEncrypter
{
    public function __construct(
        public readonly EncryptionInterface $encryption,
        public readonly ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository
    ) {
        $fileSystem = new Filesystem();
        try {
            $engineContextContent = $fileSystem->readFile('/etc/centreon-engine/engine-context.json');
            $engineContext = json_decode($engineContextContent, true, flags: JSON_THROW_ON_ERROR);
        } catch ( IOException $e) {
            throw new \RuntimeException('Cannot read engine context file: ' . $e->getMessage());
        } catch (\JsonException $e) {
            throw new \RuntimeException('Invalid engine context file: ' . $e->getMessage());
        }
        $this->encryption->setSecondKey($engineContext['salt']);
    }
}
