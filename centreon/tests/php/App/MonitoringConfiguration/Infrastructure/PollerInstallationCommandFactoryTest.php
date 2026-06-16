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
use App\MonitoringConfiguration\Domain\Aggregate\Poller\BrokerConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\ConnectorConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\EngineConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\GorgoneConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerToken;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerUid;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\TrapConfiguration;
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
        $factory = new PollerInstallationCommandFactory(
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
        $factory = new PollerInstallationCommandFactory(
            poller: $this->createPoller(PollerTypeEnum::VM),
            pollerToken: new PollerToken(name: 'test-token', value: self::POLLER_TOKEN, creationDate: new \DateTimeImmutable(), expirationDate: null, isRevoked: false),
            appSecret: self::APP_SECRET,
            salt: self::SALT,
            centralUrl: self::CENTRAL_URL,
        );

        self::assertStringContainsString('--poller_token ' . self::POLLER_TOKEN, $factory->generate());
    }

    public function testGenerateCommandContainsPollerUid(): void
    {
        $factory = new PollerInstallationCommandFactory(
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
        $factory = new PollerInstallationCommandFactory(
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
        $factory = new PollerInstallationCommandFactory(
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
        $factory = new PollerInstallationCommandFactory(
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
        $factory = new PollerInstallationCommandFactory(
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
        $factory = new PollerInstallationCommandFactory(
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
        $factory = new PollerInstallationCommandFactory(
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
        $factory = new PollerInstallationCommandFactory(
            poller: $this->createPoller(PollerTypeEnum::VM),
            pollerToken: new PollerToken(name: 'test-token', value: self::POLLER_TOKEN, creationDate: new \DateTimeImmutable(), expirationDate: null, isRevoked: false),
            appSecret: self::APP_SECRET,
            salt: self::SALT,
            centralUrl: self::CENTRAL_URL,
        );

        $expected = sprintf(
            'curl -fsSL https://%s/poller/install.sh | bash -s -- --poller_token %s --uid %s --name %s --type vm --central_url %s --appsecret %s --salt %s',
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
            brokerConfiguration: new BrokerConfiguration(),
            connectorConfiguration: new ConnectorConfiguration(),
            trapConfiguration: new TrapConfiguration(),
            pollerCommands: new Collection([], PollerCommand::class),
            engineConfiguration: new EngineConfiguration(),
            gorgoneConfiguration: new GorgoneConfiguration(),
        );
    }
}
