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

namespace App\AiConfiguration\Domain\Llm;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Domain\Exception\InvalidAiProviderConfigurationException;

/**
 * What a configuration-time check (connection test, model discovery) needs to
 * reach a provider that may not be saved yet.
 */
final readonly class ConnectionSpec
{
    public EndpointUrl $endpointUrl;

    /**
     * @param ModelName|null $model the model to address, if the check sends a request to one
     *
     * @throws InvalidAiProviderConfigurationException when the endpoint is not acceptable for the type
     */
    public function __construct(
        public AiProviderTypeEnum $type,
        EndpointUrl $endpointUrl,
        public StoredApiToken $apiToken,
        public ?ModelName $model = null,
    ) {
        // the same rule as for a saved provider: a curated provider is only
        // reached at its official endpoint
        $this->endpointUrl = $type->resolveEndpoint($endpointUrl->value);
    }
}
