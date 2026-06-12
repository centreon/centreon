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

use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * @extends AggregateRoot<AiProviderId>
 */
final class AiProvider extends AggregateRoot
{
    public function __construct(
        ?AiProviderId $id,
        public AiProviderName $name,
        public AiProviderTypeEnum $type,
        public EndpointUrl $endpointUrl,
        public ApiToken $apiToken,
        public ModelName $defaultModel,
        public ?ModelName $fastModel,
        public ?AiProviderDescription $description,
        public bool $isActivated,
    ) {
        parent::__construct($id);
    }

    public function rename(AiProviderName $name): void
    {
        $this->name = $name;
    }

    public function updateEndpointUrl(EndpointUrl $endpointUrl): void
    {
        $this->endpointUrl = $endpointUrl;
    }

    public function updateApiToken(ApiToken $apiToken): void
    {
        $this->apiToken = $apiToken;
    }

    public function updateDefaultModel(ModelName $defaultModel): void
    {
        $this->defaultModel = $defaultModel;
    }

    public function updateFastModel(?ModelName $fastModel): void
    {
        $this->fastModel = $fastModel;
    }

    public function updateDescription(?AiProviderDescription $description): void
    {
        $this->description = $description;
    }

    public function enable(): void
    {
        $this->isActivated = true;
    }

    public function disable(): void
    {
        $this->isActivated = false;
    }
}
