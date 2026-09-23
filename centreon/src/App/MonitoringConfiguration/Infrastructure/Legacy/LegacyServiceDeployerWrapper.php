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

namespace App\MonitoringConfiguration\Infrastructure\Legacy;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Exception\ServiceDeploymentFailedException;
use App\MonitoringConfiguration\Domain\Service\ServiceDeployer;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Infrastructure\Legacy\LegacyContainer;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Contact\Interfaces\ContactServiceInterface;
use Core\Service\Application\UseCase\DeployServices\DeployServices;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Webmozart\Assert\Assert;

/**
 * The only class allowed to know that service deployment is still a legacy use case.
 *
 * Three legacy facts are worked around, all verified:
 * 1. `LegacyContainer` boots a second kernel with its own connection, so the host must already be
 *    committed — the caller's contract, see {@see HostServicesDeploymentRequested}.
 * 2. Outside a legacy request the container's shared `Contact` is empty, so `DeployServices`
 *    would refuse on its topology-role check.
 * 3. The action log reads its author from the legacy token storage, not from that `Contact`, so
 *    without a token every service would be audited with none.
 *
 * Both mutations touch shared services and are undone in a `finally`, so the impersonation lasts
 * for the call rather than for the container's lifetime.
 */
final readonly class LegacyServiceDeployerWrapper implements ServiceDeployer
{
    /** Public alias of `security.untracked_token_storage`, see config/packages/Centreon.yaml. */
    private const LEGACY_TOKEN_STORAGE_SERVICE = 'centreon.legacy_token_storage';

    /** Everything setId()/setAdmin()/setTopologyRules() reach, including what they do indirectly. */
    private const IMPERSONATED_PROPERTIES = ['id', 'isAdmin', 'roles', 'topologyRulesNames'];

    public function __construct(
        private LegacyContainer $legacyContainer,
    ) {
    }

    public function deployFromTemplates(HostId $hostId, UserId $requestedBy): void
    {
        $sharedContact = $this->legacyContainer->get(ContactInterface::class);
        Assert::isInstanceOf($sharedContact, Contact::class);

        $tokenStorage = $this->legacyContainer->get(self::LEGACY_TOKEN_STORAGE_SERVICE);
        Assert::isInstanceOf($tokenStorage, TokenStorageInterface::class);

        $previousContactState = $this->snapshot($sharedContact);
        $previousToken = $tokenStorage->getToken();

        try {
            $this->impersonate($sharedContact, $tokenStorage, $requestedBy);

            $useCase = $this->legacyContainer->get(DeployServices::class);
            Assert::isInstanceOf($useCase, DeployServices::class);

            $presenter = new CapturingDeployServicesPresenter();
            $useCase($presenter, $hostId->value);

            if (($failureMessage = $presenter->failureMessage()) !== null) {
                throw new ServiceDeploymentFailedException($failureMessage);
            }
        } finally {
            $this->restore($sharedContact, $previousContactState);
            $tokenStorage->setToken($previousToken);
        }
    }

    /**
     * Reflection rather than the accessors: on the untouched instance `id` and `isAdmin` are null
     * while their getters are typed, so reading them would fatal — and `setAdmin(true)` appends to
     * `roles`, which no setter undoes.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Contact $contact): array
    {
        $state = [];
        foreach (self::IMPERSONATED_PROPERTIES as $name) {
            $state[$name] = (new \ReflectionProperty(Contact::class, $name))->getValue($contact);
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function restore(Contact $contact, array $state): void
    {
        foreach ($state as $name => $value) {
            (new \ReflectionProperty(Contact::class, $name))->setValue($contact, $value);
        }
    }

    /**
     * Fills the shared `Contact` that `DeployServices` was constructed with, and the token storage
     * the action log reads its author from.
     */
    private function impersonate(Contact $sharedContact, TokenStorageInterface $tokenStorage, UserId $userId): void
    {
        $contactService = $this->legacyContainer->get(ContactServiceInterface::class);
        Assert::isInstanceOf($contactService, ContactServiceInterface::class);

        $contact = $contactService->findContact($userId->value);
        if (! $contact instanceof Contact) {
            throw new ServiceDeploymentFailedException(sprintf('Unknown requester "%d".', $userId->value));
        }

        $sharedContact
            ->setId($contact->getId())
            ->setAdmin($contact->isAdmin())
            ->setTopologyRules($contact->getTopologyRules());

        $tokenStorage->setToken(new PreAuthenticatedToken($contact, 'legacy', $contact->getRoles()));
    }
}
