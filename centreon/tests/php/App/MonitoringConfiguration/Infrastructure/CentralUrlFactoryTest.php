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
    /** @var array<string> */
    private array $trustedProxies = [];

    private int $trustedHeaderSet = 0;

    /**
     * Request holds the trusted proxies statically, and the integration suite boots the
     * kernel, which declares them process-wide: without this the scheme resolved here
     * would depend on which tests ran before.
     */
    protected function setUp(): void
    {
        $this->trustedProxies = Request::getTrustedProxies();
        $this->trustedHeaderSet = Request::getTrustedHeaderSet();
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
    }

    protected function tearDown(): void
    {
        /** @var int-mask-of<Request::HEADER_*> $trustedHeaderSet */
        $trustedHeaderSet = $this->trustedHeaderSet;

        Request::setTrustedProxies($this->trustedProxies, $trustedHeaderSet);
    }

    public function testItAppendsThePlatformBaseUriToABareAddress(): void
    {
        $factory = $this->createFactory('https://central.example.com/centreon/api/latest/configuration/pollers');

        self::assertSame(
            'https://10.25.11.198/centreon',
            $factory->create(new CentralAddress('10.25.11.198'))->value
        );
    }

    public function testItKeepsThePortWhenAppendingTheBaseUri(): void
    {
        $factory = $this->createFactory('https://central.example.com/centreon/api/latest/configuration/pollers');

        self::assertSame(
            'https://central.example.com:8443/centreon',
            $factory->create(new CentralAddress('central.example.com:8443'))->value
        );
    }

    public function testItBracketsAnIpv6AddressSoCurlCanParseTheAuthority(): void
    {
        $factory = $this->createFactory('https://central.example.com/centreon/api/latest/configuration/pollers');

        self::assertSame(
            'https://[2001:db8::1]/centreon',
            $factory->create(new CentralAddress('2001:db8::1'))->value
        );
    }

    public function testItBracketsAnIpv6AddressCarryingItsOwnBasePath(): void
    {
        $factory = $this->createFactory('https://central.example.com/centreon/api/latest/configuration/pollers');

        self::assertSame(
            'https://[2001:db8::1]/platform',
            $factory->create(new CentralAddress('2001:db8::1/platform'))->value
        );
    }

    public function testItAppendsNothingWhenThePlatformIsRootMounted(): void
    {
        $factory = $this->createFactory('https://central.example.com/api/latest/configuration/pollers');

        self::assertSame(
            'https://10.25.11.198',
            $factory->create(new CentralAddress('10.25.11.198'))->value
        );
    }

    public function testItDoesNotDuplicateABasePathAlreadyCarriedByTheAddress(): void
    {
        $factory = $this->createFactory('https://orga.euwest1.example.com/platform/api/latest/configuration/pollers');

        self::assertSame(
            'https://orga.euwest1.example.com/platform',
            $factory->create(new CentralAddress('orga.euwest1.example.com/platform'))->value
        );
    }

    public function testItUsesTheSchemeOfTheCurrentRequest(): void
    {
        $factory = $this->createFactory('http://central.example.com/centreon/api/latest/configuration/pollers');

        self::assertSame(
            'http://10.25.11.198/centreon',
            $factory->create(new CentralAddress('10.25.11.198'))->value
        );
    }

    public function testItFallsBackToHttpsWithoutACurrentRequest(): void
    {
        $factory = new CentralUrlFactory(new RequestStack());

        self::assertSame(
            'https://10.25.11.198',
            $factory->create(new CentralAddress('10.25.11.198'))->value
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
            $factory->create(new CentralAddress('orga.euwest1.example.com/platform'))->value
        );
    }

    public function testItIgnoresABaseUriThatIsNotAPlainPath(): void
    {
        $factory = $this->createFactory('https://central.example.com/centreon;id/api/latest/configuration/pollers');

        self::assertSame(
            'https://10.25.11.198',
            $factory->create(new CentralAddress('10.25.11.198'))->value
        );
    }

    public function testItIgnoresABaseUriContainingDotSegments(): void
    {
        $factory = $this->createFactory('https://central.example.com/centreon/../api/latest/configuration/pollers');

        self::assertSame(
            'https://10.25.11.198',
            $factory->create(new CentralAddress('10.25.11.198'))->value
        );
    }

    public function testItResolvesTheBaseUriFromTheLegacyEntryPoint(): void
    {
        $factory = $this->createFactory(
            'https://central.example.com/centreon/include/configuration/configServers/copyInstallCommand.php?id=1'
        );

        self::assertSame(
            'https://10.25.11.198/centreon',
            $factory->create(new CentralAddress('10.25.11.198'))->value
        );
    }

    public function testItKeepsTheAddressBasePathOverThePlatformOne(): void
    {
        $factory = $this->createFactory('https://central.example.com/centreon/api/latest/configuration/pollers');

        self::assertSame(
            'https://10.0.0.1/custom',
            $factory->create(new CentralAddress('10.0.0.1/custom'))->value
        );
    }

    public function testItFollowsTheSchemeForwardedByATrustedProxy(): void
    {
        $this->trustTheKernelProxies();

        $factory = $this->createFactory(
            'http://central.example.com/centreon/api/latest/configuration/pollers',
            ['HTTP_X_FORWARDED_PROTO' => 'https, http']
        );

        self::assertSame(
            'https://10.25.11.198/centreon',
            $factory->create(new CentralAddress('10.25.11.198'))->value
        );
    }

    public function testItIgnoresASchemeForwardedByAnUntrustedClient(): void
    {
        $factory = $this->createFactory(
            'https://central.example.com/centreon/api/latest/configuration/pollers',
            ['HTTP_X_FORWARDED_PROTO' => 'http']
        );

        self::assertSame(
            'https://10.25.11.198/centreon',
            $factory->create(new CentralAddress('10.25.11.198'))->value
        );
    }

    /**
     * Mirrors what the kernel applies from config/packages/framework.yaml.
     */
    private function trustTheKernelProxies(): void
    {
        Request::setTrustedProxies(
            ['127.0.0.1', 'REMOTE_ADDR'],
            Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_PORT
        );
    }

    /**
     * @param array<string, string> $server
     */
    private function createFactory(string $requestUrl, array $server = []): CentralUrlFactory
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create($requestUrl, server: $server));

        return new CentralUrlFactory($requestStack);
    }
}
