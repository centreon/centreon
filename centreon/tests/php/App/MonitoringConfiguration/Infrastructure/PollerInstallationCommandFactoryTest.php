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

namespace Tests\App\MonitoringConfiguration\Infrastructure;

use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\BrokerInformation;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\ConnectorConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\EngineInformation;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\GorgoneConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerUid;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\TrapConfiguration;
use App\MonitoringConfiguration\Domain\Model\PollerToken;
use App\MonitoringConfiguration\Infrastructure\PollerInstallationCommandFactory;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;

final class PollerInstallationCommandFactoryTest extends TestCase
{
    private const CENTRAL_URL = 'centreon.example.com';
    private const POLLER_TOKEN = 'my-secure-poller-token';
    private const APP_SECRET = 'my-app-secret';
    private const SALT = 'my-salt';
    private const POLLER_UID = 123456789;
    private const POLLER_NAME = 'my-poller';

    public function testGenerateCommandContainsCurlScript(): void
    {
        $factory = PollerInstallationCommandFactory::fromPoller(
            poller: $this->createPoller(PollerTypeEnum::VM),
            pollerToken: new PollerToken(name: 'test-token', value: self::POLLER_TOKEN, creationDate: new \DateTimeImmutable(), expirationDate: null, isRevoked: false),
            appSecret: self::APP_SECRET,
            salt: self::SALT,
            centralUrl: self::CENTRAL_URL,
        );

        $command = $factory->generate();

        self::assertStringContainsString('curl -fsSL', $command);
        self::assertStringContainsString('https://' . self::CENTRAL_URL . '/poller/install.sh', $command);
        self::assertStringContainsString('| bash -s --', $command);
    }

    public function testGenerateCommandContainsPollerToken(): void
    {
        $factory = PollerInstallationCommandFactory::fromPoller(
            poller: $this->createPoller(PollerTypeEnum::VM),
            pollerToken: new PollerToken(name: 'test-token', value: self::POLLER_TOKEN, creationDate: new \DateTimeImmutable(), expirationDate: null, isRevoked: false),
            appSecret: self::APP_SECRET,
            salt: self::SALT,
            centralUrl: self::CENTRAL_URL,
        );

        self::assertStringContainsString('--poller_token test-token:' . self::POLLER_TOKEN, $factory->generate());
    }

    public function testGenerateCommandContainsPollerUid(): void
    {
        $factory = PollerInstallationCommandFactory::fromPoller(
            poller: $this->createPoller(PollerTypeEnum::VM),
            pollerToken: new PollerToken(name: 'test-token', value: self::POLLER_TOKEN, creationDate: new \DateTimeImmutable(), expirationDate: null, isRevoked: false),
            appSecret: self::APP_SECRET,
            salt: self::SALT,
            centralUrl: self::CENTRAL_URL,
        );

        self::assertStringContainsString('--uid ' . self::POLLER_UID, $factory->generate());
    }

    public function testGenerateCommandContainsPollerName(): void
    {
        $factory = PollerInstallationCommandFactory::fromPoller(
            poller: $this->createPoller(PollerTypeEnum::VM),
            pollerToken: new PollerToken(name: 'test-token', value: self::POLLER_TOKEN, creationDate: new \DateTimeImmutable(), expirationDate: null, isRevoked: false),
            appSecret: self::APP_SECRET,
            salt: self::SALT,
            centralUrl: self::CENTRAL_URL,
        );

        self::assertStringContainsString('--name ' . escapeshellarg(self::POLLER_NAME), $factory->generate());
    }

    public function testGenerateCommandContainsVmType(): void
    {
        $factory = PollerInstallationCommandFactory::fromPoller(
            poller: $this->createPoller(PollerTypeEnum::VM),
            pollerToken: new PollerToken(name: 'test-token', value: self::POLLER_TOKEN, creationDate: new \DateTimeImmutable(), expirationDate: null, isRevoked: false),
            appSecret: self::APP_SECRET,
            salt: self::SALT,
            centralUrl: self::CENTRAL_URL,
        );

        self::assertStringContainsString('--type vm', $factory->generate());
    }

    public function testGenerateCommandContainsDockerType(): void
    {
        $factory = PollerInstallationCommandFactory::fromPoller(
            poller: $this->createPoller(PollerTypeEnum::Docker),
            pollerToken: new PollerToken(name: 'test-token', value: self::POLLER_TOKEN, creationDate: new \DateTimeImmutable(), expirationDate: null, isRevoked: false),
            appSecret: self::APP_SECRET,
            salt: self::SALT,
            centralUrl: self::CENTRAL_URL,
        );

        self::assertStringContainsString('--type docker', $factory->generate());
    }

    public function testGenerateCommandContainsCentralUrl(): void
    {
        $factory = PollerInstallationCommandFactory::fromPoller(
            poller: $this->createPoller(PollerTypeEnum::VM),
            pollerToken: new PollerToken(name: 'test-token', value: self::POLLER_TOKEN, creationDate: new \DateTimeImmutable(), expirationDate: null, isRevoked: false),
            appSecret: self::APP_SECRET,
            salt: self::SALT,
            centralUrl: self::CENTRAL_URL,
        );

        self::assertStringContainsString('--central_url ' . self::CENTRAL_URL, $factory->generate());
    }

    public function testGenerateCommandContainsAppSecret(): void
    {
        $factory = PollerInstallationCommandFactory::fromPoller(
            poller: $this->createPoller(PollerTypeEnum::VM),
            pollerToken: new PollerToken(name: 'test-token', value: self::POLLER_TOKEN, creationDate: new \DateTimeImmutable(), expirationDate: null, isRevoked: false),
            appSecret: self::APP_SECRET,
            salt: self::SALT,
            centralUrl: self::CENTRAL_URL,
        );

        self::assertStringContainsString('--appsecret ' . self::APP_SECRET, $factory->generate());
    }

    public function testGenerateCommandContainsSalt(): void
    {
        $factory = PollerInstallationCommandFactory::fromPoller(
            poller: $this->createPoller(PollerTypeEnum::VM),
            pollerToken: new PollerToken(name: 'test-token', value: self::POLLER_TOKEN, creationDate: new \DateTimeImmutable(), expirationDate: null, isRevoked: false),
            appSecret: self::APP_SECRET,
            salt: self::SALT,
            centralUrl: self::CENTRAL_URL,
        );

        self::assertStringContainsString('--salt ' . self::SALT, $factory->generate());
    }

    public function testGenerateCommandHasExpectedFormat(): void
    {
        $factory = PollerInstallationCommandFactory::fromPoller(
            poller: $this->createPoller(PollerTypeEnum::VM),
            pollerToken: new PollerToken(name: 'test-token', value: self::POLLER_TOKEN, creationDate: new \DateTimeImmutable(), expirationDate: null, isRevoked: false),
            appSecret: self::APP_SECRET,
            salt: self::SALT,
            centralUrl: self::CENTRAL_URL,
        );

        $expected = sprintf(
            'curl -fsSL https://%s/poller/install.sh | bash -s -- --poller_token test-token:%s --uid %s --name %s --type vm --central_url %s --appsecret %s --salt %s',
            self::CENTRAL_URL,
            self::POLLER_TOKEN,
            self::POLLER_UID,
            escapeshellarg(self::POLLER_NAME),
            self::CENTRAL_URL,
            self::APP_SECRET,
            self::SALT,
        );

        self::assertSame($expected, $factory->generate());
    }

    public function testGenerateCommandContainsCloudFlagWhenCloudPlatform(): void
    {
        $factory = PollerInstallationCommandFactory::fromPoller(
            poller: $this->createPoller(PollerTypeEnum::VM),
            pollerToken: new PollerToken(name: 'test-token', value: self::POLLER_TOKEN, creationDate: new \DateTimeImmutable(), expirationDate: null, isRevoked: false),
            appSecret: self::APP_SECRET,
            salt: self::SALT,
            isCloudPlatform: true,
            centralUrl: self::CENTRAL_URL,
        );

        self::assertStringEndsWith('--cloud', $factory->generate());
    }

    public function testGenerateCommandDoesNotContainCloudFlagWhenOnPremise(): void
    {
        $factory = PollerInstallationCommandFactory::fromPoller(
            poller: $this->createPoller(PollerTypeEnum::VM),
            pollerToken: new PollerToken(name: 'test-token', value: self::POLLER_TOKEN, creationDate: new \DateTimeImmutable(), expirationDate: null, isRevoked: false),
            appSecret: self::APP_SECRET,
            salt: self::SALT,
            isCloudPlatform: false,
            centralUrl: self::CENTRAL_URL,
        );

        self::assertStringNotContainsString('--cloud', $factory->generate());
    }

    public function testConstructorAndFromPollerShareTheSameDefaults(): void
    {
        $poller = $this->createPoller(PollerTypeEnum::Docker);
        $token = new PollerToken(name: 'test-token', value: self::POLLER_TOKEN, creationDate: new \DateTimeImmutable(), expirationDate: null, isRevoked: false);

        // isCloudPlatform and centralUrl are intentionally omitted: their defaults are
        // duplicated in both signatures, and this test pins them to the same values.
        $fromPoller = PollerInstallationCommandFactory::fromPoller(
            poller: $poller,
            pollerToken: $token,
            appSecret: self::APP_SECRET,
            salt: self::SALT,
        );

        $fromValueObjects = new PollerInstallationCommandFactory(
            pollerUid: $poller->uid,
            pollerName: $poller->name,
            pollerType: $poller->pollerType,
            pollerToken: $token,
            appSecret: self::APP_SECRET,
            salt: self::SALT,
        );

        self::assertSame($fromPoller->generate(), $fromValueObjects->generate());
        self::assertStringContainsString('--central_url <CENTRAL_URL>', $fromValueObjects->generate());
        self::assertStringNotContainsString('--cloud', $fromValueObjects->generate());
    }

    private function createPoller(PollerTypeEnum $type): Poller
    {
        return new Poller(
            id: new PollerId(1),
            name: new PollerName(self::POLLER_NAME),
            address: new PollerAddress('192.168.1.1'),
            isCentral: false,
            isDefault: false,
            isActivated: true,
            pollerType: $type,
            uid: new PollerUid(self::POLLER_UID),
            globalMacros: new Collection([], GlobalMacro::class),
            brokerInformation: new BrokerInformation(),
            connectorConfiguration: new ConnectorConfiguration(),
            trapConfiguration: new TrapConfiguration(),
            pollerCommands: new Collection([], PollerCommand::class),
            engineInformation: new EngineInformation(),
            gorgoneConfiguration: new GorgoneConfiguration(),
        );
    }
}
