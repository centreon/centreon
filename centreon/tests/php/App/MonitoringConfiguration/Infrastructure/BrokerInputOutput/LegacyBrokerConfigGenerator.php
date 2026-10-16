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

namespace Tests\App\MonitoringConfiguration\Infrastructure\BrokerInputOutput;

use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Security\Interfaces\EncryptionInterface;

/**
 * Runs the real legacy broker configuration generation (www/class/config-generate/broker.class.php)
 * against the test database and returns the generated files.
 *
 * The constructors of Broker and Backend need a deployed platform (engine context file, web kernel,
 * cache directories), so both are instantiated without them and only their collaborators are replaced:
 * the database connections, the output directory, the vault and the encryption (crypt() returns ENCRYPTED_PREFIX + value). All the SQL reading
 * and the transformation of broker flows run unchanged.
 */
final class LegacyBrokerConfigGenerator
{
    /** Marker replacing the real encryption so that encrypted values stay readable in expectations. */
    public const ENCRYPTED_PREFIX = 'test-encrypted:';

    private readonly string $outputDirectory;

    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param ReadVaultRepositoryInterface|null $vaultRepository null when the vault is not enabled
     */
    public function __construct(
        private readonly BrokerTestDatabase $database,
        private readonly ReadMonitoringServerRepositoryInterface $monitoringServerRepository,
        private readonly ?ReadVaultRepositoryInterface $vaultRepository = null,
    ) {
        $this->outputDirectory = sys_get_temp_dir() . '/broker-io-generation-' . bin2hex(random_bytes(6));
    }

    /**
     * @return array<string, array<mixed>> decoded generated files by file name (broker configurations and watchdog.json)
     */
    public function generate(int $pollerId, bool $localhost): array
    {
        if (! mkdir($this->outputDirectory, 0770, true) && ! is_dir($this->outputDirectory)) {
            throw new \RuntimeException('Cannot create ' . $this->outputDirectory);
        }

        $this->warnings = [];
        set_error_handler(function (int $level, string $message, string $file, int $line): bool {
            $this->warnings[] = sprintf('%s:%d %s', basename($file), $line, $message);

            return true;
        }, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE);

        try {
            try {
                $this->createBroker()->generateFromPoller(['id' => $pollerId, 'localhost' => $localhost ? '1' : '0']);
            } finally {
                restore_error_handler();
            }

            $files = [];
            foreach (glob($this->outputDirectory . '/*.json') ?: [] as $file) {
                $content = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
                $files[basename($file)] = is_array($content) ? $content : throw new \RuntimeException('Invalid ' . $file);
            }
            ksort($files);

            return $files;
        } finally {
            array_map('unlink', glob($this->outputDirectory . '/*') ?: []);
            rmdir($this->outputDirectory);
        }
    }

    /**
     * PHP warnings and notices raised by the last generation, which are part of the current behavior.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    private function createBroker(): \Broker
    {
        $backend = (new \ReflectionClass(\Backend::class))->newInstanceWithoutConstructor();
        $backend->db = $this->database->configuration;
        $backend->db_cs = $this->database->realtime;
        (new \ReflectionProperty(\Backend::class, 'full_path'))->setValue($backend, $this->outputDirectory);

        $vaultConfiguration = new class ($this->vaultRepository instanceof ReadVaultRepositoryInterface) implements ReadVaultConfigurationRepositoryInterface {
            public function __construct(private readonly bool $exists)
            {
            }

            public function exists(): bool
            {
                return $this->exists;
            }

            public function find(): ?\Core\Security\Vault\Domain\Model\VaultConfiguration
            {
                return null;
            }

            public function getLocation(): ?string
            {
                return $this->exists ? '/etc/centreon-broker/vault-test.json' : null;
            }
        };

        $broker = (new \ReflectionClass(\Broker::class))->newInstanceWithoutConstructor();
        $this->setProperty($broker, 'backend_instance', $backend);
        $this->setProperty($broker, 'readVaultConfigurationRepository', $vaultConfiguration);
        $this->setProperty($broker, 'readMonitoringServerRepository', $this->monitoringServerRepository);
        $this->setProperty($broker, 'engineContextEncryption', $this->encryption());
        $this->setProperty($broker, 'isVaultEnabled', $this->vaultRepository instanceof ReadVaultRepositoryInterface);
        $this->setProperty($broker, 'readVaultRepository', $this->vaultRepository);

        return $broker;
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        (new \ReflectionProperty($object, $name))->setValue($object, $value);
    }

    private function encryption(): EncryptionInterface
    {
        return new class () implements EncryptionInterface {
            public static function generateRandomString(int $length = 64): string
            {
                return str_repeat('x', $length);
            }

            public function crypt(string $data): string
            {
                return LegacyBrokerConfigGenerator::ENCRYPTED_PREFIX . $data;
            }

            public function setFirstKey(string $firstKey): EncryptionInterface
            {
                return $this;
            }

            public function setSecondKey(string $secondKey): EncryptionInterface
            {
                return $this;
            }

            public function decrypt(string $input): ?string
            {
                return null;
            }
        };
    }
}
