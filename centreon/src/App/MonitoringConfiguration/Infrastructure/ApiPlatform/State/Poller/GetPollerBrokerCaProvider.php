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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerUid;
use App\MonitoringConfiguration\Domain\Exception\PollerTokenNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\PollerBrokerChannelRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerTokenRepository;
use App\MonitoringConfiguration\Domain\Service\BrokerCaProvider;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Poller\PollerBrokerCaResource;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * MON-215450 phase 2g (contracts.md § 11.W): the CA is public material, but the route answers only to a valid
 * poller token, checked before the uid so that an unknown uid tells nothing to an unauthenticated caller.
 *
 * @template-implements ProviderInterface<PollerBrokerCaResource>
 */
final readonly class GetPollerBrokerCaProvider implements ProviderInterface
{
    public const TOKEN_HEADER = 'X-Centreon-Poller-Token';

    /** A poller uid is a positive BIGINT: at most 19 digits */
    private const UID_PATTERN = '/^[1-9]\d{0,18}$/D';

    public function __construct(
        private RequestStack $requestStack,
        private PollerTokenRepository $pollerTokenRepository,
        private PollerBrokerChannelRepository $pollerBrokerChannelRepository,
        private BrokerCaProvider $brokerCaProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PollerBrokerCaResource
    {
        $this->checkPollerToken();

        $rawUid = $uriVariables['uid'] ?? null;
        $rawUid = is_int($rawUid) || is_string($rawUid) ? (string) $rawUid : '';
        if (preg_match(self::UID_PATTERN, $rawUid) !== 1 || (int) $rawUid <= 0) {
            throw new NotFoundHttpException('Poller not found');
        }
        $uid = new PollerUid((int) $rawUid);
        if (! $this->pollerBrokerChannelRepository->findPollerIdByUid($uid) instanceof PollerId) {
            throw new NotFoundHttpException('Poller not found');
        }

        ['ca' => $brokerCa] = $this->brokerCaProvider->read();
        if ($brokerCa === null) {
            throw new ServiceUnavailableHttpException(null, 'Central broker CA unavailable');
        }

        return new PollerBrokerCaResource(
            uid: $uid->value,
            pem: $brokerCa['pem'],
            fingerprintSha256: $brokerCa['fingerprint_sha256'],
        );
    }

    /**
     * Same rule as the token embedded in the installation command: a poller token, valid (not revoked, not
     * expired), found by name, with a matching value. The name may contain ':', the base64 value cannot.
     */
    private function checkPollerToken(): void
    {
        $header = trim((string) $this->requestStack->getCurrentRequest()?->headers->get(self::TOKEN_HEADER));
        $separator = mb_strrpos($header, ':');
        if ($separator === false || $separator === 0 || $separator === mb_strlen($header) - 1) {
            throw $this->invalidToken();
        }
        $tokenName = mb_substr($header, 0, $separator);
        $tokenValue = mb_substr($header, $separator + 1);

        try {
            $token = $this->pollerTokenRepository->getValidPollerTokenByName($tokenName);
        } catch (PollerTokenNotFoundException) {
            throw $this->invalidToken();
        }

        if (! hash_equals($token->value, $tokenValue)) {
            throw $this->invalidToken();
        }
    }

    private function invalidToken(): UnauthorizedHttpException
    {
        return new UnauthorizedHttpException(self::TOKEN_HEADER, 'Invalid poller token');
    }
}
