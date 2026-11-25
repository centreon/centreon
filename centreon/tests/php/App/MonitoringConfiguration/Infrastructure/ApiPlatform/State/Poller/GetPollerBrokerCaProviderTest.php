<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller;

use ApiPlatform\Metadata\Get;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Exception\PollerTokenNotFoundException;
use App\MonitoringConfiguration\Domain\Model\PollerToken;
use App\MonitoringConfiguration\Domain\Repository\PollerBrokerChannelRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerTokenRepository;
use App\MonitoringConfiguration\Domain\Service\BrokerCaProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Poller\PollerBrokerCaResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller\GetPollerBrokerCaProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class GetPollerBrokerCaProviderTest extends TestCase
{
    private const UID = '123456789012345';
    private const PEM = "-----BEGIN CERTIFICATE-----\nMIIB\n-----END CERTIFICATE-----\n";

    private PollerTokenRepository $tokenRepository;

    private PollerBrokerChannelRepository $channelRepository;

    private BrokerCaProvider $caProvider;

    protected function setUp(): void
    {
        $tokenRepository = $this->createStub(PollerTokenRepository::class);
        $tokenRepository->method('getValidPollerTokenByName')->willReturnCallback(
            static function (string $name): PollerToken {
                if ($name !== 'my:token') {
                    throw new PollerTokenNotFoundException([], 'not found');
                }

                return new PollerToken($name, 'VALUE+/=', new \DateTimeImmutable(), null, false);
            }
        );
        $this->tokenRepository = $tokenRepository;
        $this->channelRepository = $this->channelRepositoryFinding(new PollerId(42));
        $this->caProvider = $this->caProviderReading([
            'ca' => ['pem' => self::PEM, 'fingerprint_sha256' => str_repeat('ab', 32)],
            'warning' => null,
        ]);
    }

    public function testValidTokenGetsTheCa(): void
    {
        $resource = $this->provide('my:token:VALUE+/=', self::UID);

        self::assertSame((int) self::UID, $resource->uid);
        self::assertSame(self::PEM, $resource->pem);
        self::assertSame(str_repeat('ab', 32), $resource->fingerprintSha256);
    }

    public function testMissingTokenIsUnauthorized(): void
    {
        $this->expectException(UnauthorizedHttpException::class);

        $this->provide(null, self::UID);
    }

    public function testWrongTokenValueIsUnauthorized(): void
    {
        $this->expectException(UnauthorizedHttpException::class);

        $this->provide('my:token:WRONG', self::UID);
    }

    public function testUnknownTokenNameIsUnauthorizedEvenForAnUnknownUid(): void
    {
        $this->channelRepository = $this->channelRepositoryFinding(null);

        $this->expectException(UnauthorizedHttpException::class);

        $this->provide('other:VALUE+/=', '999');
    }

    public function testUnknownUidIsNotFound(): void
    {
        $this->channelRepository = $this->channelRepositoryFinding(null);

        $this->expectException(NotFoundHttpException::class);

        $this->provide('my:token:VALUE+/=', '999');
    }

    public function testInvalidUidIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->provide('my:token:VALUE+/=', '12abc');
    }

    public function testUnavailableCaIsServiceUnavailable(): void
    {
        $this->caProvider = $this->caProviderReading(['ca' => null, 'warning' => 'cbd down']);

        $this->expectException(ServiceUnavailableHttpException::class);

        $this->provide('my:token:VALUE+/=', self::UID);
    }

    private function channelRepositoryFinding(?PollerId $pollerId): PollerBrokerChannelRepository
    {
        $channelRepository = $this->createStub(PollerBrokerChannelRepository::class);
        $channelRepository->method('findPollerIdByUid')->willReturn($pollerId);

        return $channelRepository;
    }

    /**
     * @param array{ca: array{pem: string, fingerprint_sha256: string}|null, warning: string|null} $answer
     */
    private function caProviderReading(array $answer): BrokerCaProvider
    {
        $caProvider = $this->createStub(BrokerCaProvider::class);
        $caProvider->method('read')->willReturn($answer);

        return $caProvider;
    }

    private function provide(?string $tokenHeader, string $uid): PollerBrokerCaResource
    {
        $request = new Request();
        if ($tokenHeader !== null) {
            $request->headers->set(GetPollerBrokerCaProvider::TOKEN_HEADER, $tokenHeader);
        }
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $provider = new GetPollerBrokerCaProvider(
            $requestStack,
            $this->tokenRepository,
            $this->channelRepository,
            $this->caProvider,
        );

        return $provider->provide(new Get(), ['uid' => $uid]);
    }
}
