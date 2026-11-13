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

namespace Tests\App\Upgrade\Infrastructure\Log;

use App\Upgrade\Domain\Service\UpgradeLoggerInterface;
use App\Upgrade\Infrastructure\Doctrine\MigrationFactoryProvider;
use App\Upgrade\Infrastructure\Doctrine\MigrationSetRegistry;
use App\Upgrade\Infrastructure\Log\FailSafeLogger;
use App\Upgrade\Infrastructure\Log\UpgradeLogger;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UpgradeLoggerKernelTest extends KernelTestCase
{
    public function testWritesToTheUpgradeChannel(): void
    {
        $upgradeLogger = self::getContainer()->get(UpgradeLoggerInterface::class);
        self::assertInstanceOf(UpgradeLogger::class, $upgradeLogger);

        $failSafeLogger = (new \ReflectionProperty(UpgradeLogger::class, 'upgradeLogger'))->getValue($upgradeLogger);
        self::assertInstanceOf(FailSafeLogger::class, $failSafeLogger);

        $logger = (new \ReflectionProperty(FailSafeLogger::class, 'logger'))->getValue($failSafeLogger);
        self::assertInstanceOf(Logger::class, $logger);
        self::assertSame('upgrade', $logger->getName());
    }

    public function testTheDoctrineLinesGoThroughTheFailSafeLogger(): void
    {
        foreach ([MigrationFactoryProvider::class, MigrationSetRegistry::class] as $service) {
            $logger = (new \ReflectionProperty($service, 'logger'))->getValue(self::getContainer()->get($service));
            self::assertInstanceOf(FailSafeLogger::class, $logger, $service);
        }
    }
}
