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
use App\AiConfiguration\Domain\Exception\InvalidAiProviderConfigurationException;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * @extends AggregateRoot<AiProviderId>
 */
final class AiProvider extends AggregateRoot
{
    /**
     * Rebuilds a provider as it is stored. Its endpoint and token are checked when
     * they are set (create(), retarget()), not here: a rule tightened later must not
     * make a stored provider unreadable, and so impossible to fix or delete.
     */
    public function __construct(
        ?AiProviderId $id,
        private AiProviderName $name,
        private readonly AiProviderTypeEnum $type,
        private EndpointUrl $endpointUrl,
        private ?StoredApiToken $apiToken,
        private ModelName $defaultModel,
        private ?ModelName $fastModel,
        private ?AiProviderDescription $description,
        private bool $isActivated,
        private Billing $billing = new Billing(),
        private ReasoningModeEnum $defaultModelReasoning = ReasoningModeEnum::Minimal,
        private ReasoningModeEnum $fastModelReasoning = ReasoningModeEnum::Minimal,
    ) {
        parent::__construct($id);
    }

    /**
     * @param StoredApiToken|null $apiToken null for a server that needs none, if the type allows it
     *
     * @throws InvalidAiProviderConfigurationException when the endpoint does not fit the type, or
     *                                                 the type needs a token and there is none
     */
    public static function create(
        AiProviderName $name,
        AiProviderTypeEnum $type,
        EndpointUrl $endpointUrl,
        ?StoredApiToken $apiToken,
        ModelName $defaultModel,
        ?ModelName $fastModel,
        ?AiProviderDescription $description,
        bool $isActivated,
        Billing $billing = new Billing(),
        ReasoningModeEnum $defaultModelReasoning = ReasoningModeEnum::Minimal,
        ReasoningModeEnum $fastModelReasoning = ReasoningModeEnum::Minimal,
    ): self {
        self::assertEndpointFitsType($type, $endpointUrl);
        if (! $apiToken instanceof StoredApiToken && $type->requiresApiToken()) {
            throw InvalidAiProviderConfigurationException::apiTokenRequired($type);
        }

        return new self(
            null,
            $name,
            $type,
            $endpointUrl,
            $apiToken,
            $defaultModel,
            $fastModel,
            $description,
            $isActivated,
            $billing,
            $defaultModelReasoning,
            $fastModelReasoning,
        );
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

    /**
     * Null for a server that needs none.
     */
    public function apiToken(): ?StoredApiToken
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

    public function defaultModelReasoning(): ReasoningModeEnum
    {
        return $this->defaultModelReasoning;
    }

    /**
     * Ignored without a fast model: the fast tier is then served by the default model, with its setting.
     */
    public function fastModelReasoning(): ReasoningModeEnum
    {
        return $this->fastModelReasoning;
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

    public function changeModels(
        ModelName $defaultModel,
        ?ModelName $fastModel,
        ReasoningModeEnum $defaultModelReasoning,
        ReasoningModeEnum $fastModelReasoning,
    ): void {
        $this->defaultModel = $defaultModel;
        $this->fastModel = $fastModel;
        $this->defaultModelReasoning = $defaultModelReasoning;
        $this->fastModelReasoning = $fastModelReasoning;
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
     * one, which is only allowed while the target does not change. A type whose
     * token is optional may have it removed instead, explicitly: a token is never
     * lost because it was left out.
     *
     * @param StoredApiToken|null $apiToken the new token, or null to keep the stored one
     * @param bool $removeApiToken remove the stored token (ignored when a new one is given)
     *
     * @throws AiProviderCanNotBeUpdatedException when the endpoint changes without a new token,
     *                                            unless the stored one is removed (or there is none)
     * @throws InvalidAiProviderConfigurationException when the endpoint does not fit the type, or
     *                                                 the token of a type that needs one is removed
     */
    public function retarget(EndpointUrl $endpointUrl, ?StoredApiToken $apiToken, bool $removeApiToken = false): void
    {
        self::assertEndpointFitsType($this->type, $endpointUrl);

        $removesApiToken = $removeApiToken && ! $apiToken instanceof StoredApiToken;
        if ($removesApiToken && $this->type->requiresApiToken()) {
            throw InvalidAiProviderConfigurationException::apiTokenRequired($this->type);
        }
        // with no stored token, there is nothing to keep from the new endpoint
        if (! $apiToken instanceof StoredApiToken && ! $removesApiToken
            && $this->apiToken instanceof StoredApiToken
            && ! $this->canReuseApiTokenFor($this->type, $endpointUrl)
        ) {
            throw AiProviderCanNotBeUpdatedException::apiTokenRequiredForNewEndpoint($this->id());
        }

        $this->endpointUrl = $endpointUrl;
        $this->apiToken = $apiToken ?? ($removesApiToken ? null : $this->apiToken);
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

    /**
     * @throws InvalidAiProviderConfigurationException
     */
    private static function assertEndpointFitsType(AiProviderTypeEnum $type, EndpointUrl $endpointUrl): void
    {
        if (! $type->resolveEndpoint($endpointUrl->value)->equals($endpointUrl)) {
            throw InvalidAiProviderConfigurationException::endpointNotAllowed($type);
        }
    }
}
