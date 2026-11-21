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

namespace App\AiConfiguration\Domain\Aggregate\AiProvider;

use App\AiConfiguration\Domain\Exception\AiProviderCanNotBeUpdatedException;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * @extends AggregateRoot<AiProviderId>
 */
final class AiProvider extends AggregateRoot
{
    public function __construct(
        ?AiProviderId $id,
        private AiProviderName $name,
        private readonly AiProviderTypeEnum $type,
        private EndpointUrl $endpointUrl,
        private StoredApiToken $apiToken,
        private ModelName $defaultModel,
        private ?ModelName $fastModel,
        private ?AiProviderDescription $description,
        private bool $isActivated,
        private Billing $billing = new Billing(),
    ) {
        parent::__construct($id);
    }

    public function name(): AiProviderName
    {
        return $this->name;
    }

    public function type(): AiProviderTypeEnum
    {
        return $this->type;
    }

    public function endpointUrl(): EndpointUrl
    {
        return $this->endpointUrl;
    }

    public function apiToken(): StoredApiToken
    {
        return $this->apiToken;
    }

    public function defaultModel(): ModelName
    {
        return $this->defaultModel;
    }

    public function fastModel(): ?ModelName
    {
        return $this->fastModel;
    }

    public function description(): ?AiProviderDescription
    {
        return $this->description;
    }

    public function isActivated(): bool
    {
        return $this->isActivated;
    }

    public function billing(): Billing
    {
        return $this->billing;
    }

    public function rename(AiProviderName $name): void
    {
        $this->name = $name;
    }

    public function describe(?AiProviderDescription $description): void
    {
        $this->description = $description;
    }

    public function changeModels(ModelName $defaultModel, ?ModelName $fastModel): void
    {
        $this->defaultModel = $defaultModel;
        $this->fastModel = $fastModel;
    }

    public function changeBilling(Billing $billing): void
    {
        $this->billing = $billing;
    }

    public function activate(): void
    {
        $this->isActivated = true;
    }

    public function deactivate(): void
    {
        $this->isActivated = false;
    }

    /**
     * Point the provider at an endpoint, with a new token or keeping the stored
     * one, which is only allowed while the target does not change.
     *
     * @param StoredApiToken|null $apiToken the new token, or null to keep the stored one
     *
     * @throws AiProviderCanNotBeUpdatedException when the endpoint changes without a new token
     */
    public function retarget(EndpointUrl $endpointUrl, ?StoredApiToken $apiToken): void
    {
        if (! $apiToken instanceof StoredApiToken && ! $this->canReuseApiTokenFor($this->type, $endpointUrl)) {
            throw AiProviderCanNotBeUpdatedException::apiTokenRequiredForNewEndpoint($this->id());
        }

        $this->endpointUrl = $endpointUrl;
        $this->apiToken = $apiToken ?? $this->apiToken;
    }

    /**
     * Whether the stored API token may be sent to the given provider. The token
     * is write-only: reusing it for another type or endpoint would let whoever
     * edits the configuration send it to a server they control, so a different
     * target requires the token to be typed again.
     */
    public function canReuseApiTokenFor(AiProviderTypeEnum $type, EndpointUrl $endpointUrl): bool
    {
        return $type === $this->type && $this->endpointUrl->equals($endpointUrl);
    }
}
