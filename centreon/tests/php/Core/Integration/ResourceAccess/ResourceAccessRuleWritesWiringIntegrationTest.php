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

namespace Tests\Core\Integration\ResourceAccess;

use Centreon\Infrastructure\DatabaseConnection;
use Core\ResourceAccess\Application\UseCase\AddRule\AddRule;
use Core\ResourceAccess\Application\UseCase\UpdateRule\UpdateRule;
use Core\ResourceAccess\Infrastructure\Repository\Dataset\DbWriteImageFolderRepository;
use Core\ResourceAccess\Infrastructure\Repository\DbWriteResourceAccessRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Saving a Resource Access rule must be atomic: the transaction of the AddRule and UpdateRule use
 * cases and every write of their repositories must run on the same SQL connection. When they did
 * not, creating a rule with an image folder hung on a lock wait, and updating one left it half
 * saved (MON-213035).
 *
 * This is guaranteed by the service wiring only, which unit tests cannot see: this test reads it
 * from the container.
 *
 * Run inside the Docker container:
 *   docker exec centreon-app bash -c "cd /usr/share/centreon && vendor/bin/phpunit -c phpunit.core-integration.xml --filter ResourceAccessRuleWritesWiringIntegrationTest"
 */
final class ResourceAccessRuleWritesWiringIntegrationTest extends KernelTestCase
{
    public function testUpdateRuleRunsItsTransactionAndAllItsWritesOnTheLegacyConnection(): void
    {
        $container = self::getContainer();
        $legacyConnection = $container->get(DatabaseConnection::class);

        // fails if the use case cannot be built, e.g. when a scalar argument is not wired
        $useCase = $container->get(UpdateRule::class);
        self::assertInstanceOf(UpdateRule::class, $useCase);

        $repositoryManager = self::readProperty($useCase, 'repositoryManager');
        self::assertIsObject($repositoryManager);
        self::assertSame(
            $legacyConnection,
            self::readProperty($repositoryManager, 'connection'),
            'The UpdateRule transaction must run on the connection of the write repositories.'
        );

        $this->assertWriteRepositoryUsesConnection(self::readProperty($useCase, 'writeRepository'), $legacyConnection);
    }

    public function testAddRuleRunsItsTransactionAndAllItsWritesOnTheLegacyConnection(): void
    {
        $container = self::getContainer();
        $legacyConnection = $container->get(DatabaseConnection::class);

        $useCase = $container->get(AddRule::class);
        self::assertInstanceOf(AddRule::class, $useCase);

        $dataStorageEngine = self::readProperty($useCase, 'dataStorageEngine');
        self::assertIsObject($dataStorageEngine);
        self::assertSame(
            $legacyConnection,
            self::readProperty($dataStorageEngine, 'db'),
            'The AddRule transaction must run on the connection of the write repositories.'
        );

        $this->assertWriteRepositoryUsesConnection(self::readProperty($useCase, 'writeRepository'), $legacyConnection);
    }

    private function assertWriteRepositoryUsesConnection(mixed $writeRepository, object $connection): void
    {
        self::assertInstanceOf(DbWriteResourceAccessRepository::class, $writeRepository);
        self::assertSame($connection, self::readProperty($writeRepository, 'db'));

        $datasetRepositories = self::readProperty($writeRepository, 'repositoryProviders');
        self::assertIsArray($datasetRepositories);

        $imageFolderRepositoryFound = false;
        foreach ($datasetRepositories as $datasetRepository) {
            self::assertIsObject($datasetRepository);
            if ($datasetRepository instanceof DbWriteImageFolderRepository) {
                $imageFolderRepositoryFound = true;
                self::assertSame(
                    $connection,
                    self::readProperty($datasetRepository, 'connection'),
                    'Image folders must be written on the connection of the rule transaction.'
                );

                continue;
            }

            self::assertSame(
                $connection,
                self::readProperty($datasetRepository, 'db'),
                $datasetRepository::class . ' must write on the connection of the rule transaction.'
            );
        }

        self::assertTrue($imageFolderRepositoryFound, 'The image folder dataset repository must be registered.');
    }

    /**
     * Reads a property declared by the class of the object or by one of its parents, whatever its
     * visibility.
     */
    private static function readProperty(object $object, string $name): mixed
    {
        for ($class = new \ReflectionClass($object); $class !== false; $class = $class->getParentClass()) {
            if ($class->hasProperty($name)) {
                return $class->getProperty($name)->getValue($object);
            }
        }

        self::fail(sprintf('%s has no property "%s".', $object::class, $name));
    }
}
