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

use App\MonitoringConfiguration\Domain\Aggregate\Poller\CentralAddress;
use App\MonitoringConfiguration\Infrastructure\CentralUrlFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CentralUrlFactoryTest extends TestCase
{
    public function testItAppendsThePlatformBaseUriToABareAddress(): void
    {
        $factory = $this->createFactory('https://central.example.com/centreon/api/latest/configuration/pollers');

        self::assertSame(
            'https://10.25.11.198/centreon',
            $factory->create(new CentralAddress('10.25.11.198'))
        );
    }

    public function testItKeepsThePortWhenAppendingTheBaseUri(): void
    {
        $factory = $this->createFactory('https://central.example.com/centreon/api/latest/configuration/pollers');

        self::assertSame(
            'https://central.example.com:8443/centreon',
            $factory->create(new CentralAddress('central.example.com:8443'))
        );
    }

    public function testItAppendsNothingWhenThePlatformIsRootMounted(): void
    {
        $factory = $this->createFactory('https://central.example.com/api/latest/configuration/pollers');

        self::assertSame(
            'https://10.25.11.198',
            $factory->create(new CentralAddress('10.25.11.198'))
        );
    }

    public function testItDoesNotDuplicateABasePathAlreadyCarriedByTheAddress(): void
    {
        $factory = $this->createFactory('https://orga.euwest1.example.com/platform/api/latest/configuration/pollers');

        self::assertSame(
            'https://orga.euwest1.example.com/platform',
            $factory->create(new CentralAddress('orga.euwest1.example.com/platform'))
        );
    }

    public function testItUsesTheSchemeOfTheCurrentRequest(): void
    {
        $factory = $this->createFactory('http://central.example.com/centreon/api/latest/configuration/pollers');

        self::assertSame(
            'http://10.25.11.198/centreon',
            $factory->create(new CentralAddress('10.25.11.198'))
        );
    }

    public function testItFallsBackToHttpsWithoutACurrentRequest(): void
    {
        $factory = new CentralUrlFactory(new RequestStack());

        self::assertSame(
            'https://10.25.11.198',
            $factory->create(new CentralAddress('10.25.11.198'))
        );
    }

    public function testItAlwaysUsesHttpsOnCloudPlatforms(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(
            Request::create('http://orga.euwest1.example.com/platform/api/latest/configuration/pollers')
        );

        $factory = new CentralUrlFactory($requestStack, isCloudPlatform: true);

        self::assertSame(
            'https://orga.euwest1.example.com/platform',
            $factory->create(new CentralAddress('orga.euwest1.example.com/platform'))
        );
    }

    public function testItIgnoresABaseUriThatIsNotAPlainPath(): void
    {
        $factory = $this->createFactory('https://central.example.com/centreon;id/api/latest/configuration/pollers');

        self::assertSame(
            'https://10.25.11.198',
            $factory->create(new CentralAddress('10.25.11.198'))
        );
    }

    public function testItIgnoresABaseUriContainingDotSegments(): void
    {
        $factory = $this->createFactory('https://central.example.com/centreon/../api/latest/configuration/pollers');

        self::assertSame(
            'https://10.25.11.198',
            $factory->create(new CentralAddress('10.25.11.198'))
        );
    }

    public function testItResolvesTheBaseUriFromTheLegacyEntryPoint(): void
    {
        $factory = $this->createFactory(
            'https://central.example.com/centreon/include/configuration/configServers/copyInstallCommand.php?id=1'
        );

        self::assertSame(
            'https://10.25.11.198/centreon',
            $factory->create(new CentralAddress('10.25.11.198'))
        );
    }

    public function testItKeepsTheAddressBasePathOverThePlatformOne(): void
    {
        $factory = $this->createFactory('https://central.example.com/centreon/api/latest/configuration/pollers');

        self::assertSame(
            'https://10.0.0.1/custom',
            $factory->create(new CentralAddress('10.0.0.1/custom'))
        );
    }

    public function testItUpgradesToHttpsWhenAProxyForwardsIt(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create(
            'http://central.example.com/centreon/api/latest/configuration/pollers',
            server: ['HTTP_X_FORWARDED_PROTO' => 'https, http']
        ));

        $factory = new CentralUrlFactory($requestStack);

        self::assertSame(
            'https://10.25.11.198/centreon',
            $factory->create(new CentralAddress('10.25.11.198'))
        );
    }

    public function testItNeverDowngradesAnHttpsRequestToHttp(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create(
            'https://central.example.com/centreon/api/latest/configuration/pollers',
            server: ['HTTP_X_FORWARDED_PROTO' => 'http']
        ));

        $factory = new CentralUrlFactory($requestStack);

        self::assertSame(
            'https://10.25.11.198/centreon',
            $factory->create(new CentralAddress('10.25.11.198'))
        );
    }

    private function createFactory(string $requestUrl): CentralUrlFactory
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create($requestUrl));

        return new CentralUrlFactory($requestStack);
    }
}
