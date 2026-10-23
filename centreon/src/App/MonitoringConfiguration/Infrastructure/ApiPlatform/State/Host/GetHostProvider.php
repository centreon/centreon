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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostResource;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * Returns the full detail of one host. The response body is identical in shape to the CreateHost
 * *response* (not its request): both go through HostResourceTransformer, which turns the ids the
 * aggregate carries into the named `{id, name}` outputs the contract exposes. The asymmetry is
 * intentional — CreateHost's *request* takes bare ids (`poller_id`, `template_id`, …), while both
 * its response and this read expose the richer objects (`poller: {id, name}`, …) the host form
 * needs. Secrets (snmpCommunity, password macros) are never part of HostResource and so are never
 * surfaced.
 *
 * @phpstan-import-type ExtraDataTypeAlias from HostResourceTransformer as HostResourceExtraDataTypeAlias
 *
 * @implements ProviderInterface<HostResource>
 */
final readonly class GetHostProvider implements ProviderInterface
{
    /**
     * @param TransformerInterface<Host, HostResource, HostResourceExtraDataTypeAlias> $transformer
     */
    public function __construct(
        #[Autowire(service: HostResourceTransformer::class)]
        private TransformerInterface $transformer,
        private Security $security,
        private HostRepository $hostRepository,
        private HostResourceExtraDataFetcher $extraDataFetcher,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): HostResource
    {
        Assert::integer($uriVariables['id']);
        $hostId = new HostId($uriVariables['id']);

        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);

        // Admin (unrestricted) sees every host; a restricted viewer is scoped in the query, and an
        // out-of-scope host comes back null, indistinguishable from a missing one (no existence leak).
        $viewerId = $credentialUser->credential->hasUnrestrictedResourceAccess()
            ? null
            : $credentialUser->credential->userId;

        $host = $this->hostRepository->findOne($hostId, $viewerId);
        if (! $host instanceof Host) {
            throw new HostNotFoundException([$hostId->value], 'id');
        }

        // The aggregate carries the host's full associations; here, on the read side, a restricted
        // viewer's view is narrowed to what they may access. Keeping this in the read model, not in
        // findOne(), leaves the aggregate whole for the write paths that also load it.
        return $this->transformer->transform($host, $this->extraDataFetcher->fetch($host, $viewerId));
    }
}
