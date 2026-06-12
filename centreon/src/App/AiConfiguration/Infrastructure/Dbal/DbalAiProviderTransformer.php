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

namespace App\AiConfiguration\Infrastructure\Dbal;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderDescription;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\Shared\Infrastructure\TransformerInterface;
use Webmozart\Assert\Assert;

/**
 * @phpstan-import-type RowTypeAlias from DbalAiProviderRepository
 *
 * @implements TransformerInterface<RowTypeAlias, AiProvider>
 */
final readonly class DbalAiProviderTransformer implements TransformerInterface
{
    /**
     * @param RowTypeAlias $from
     */
    public function transform(mixed $from): AiProvider
    {
        /** @var array{endpoint_url: string, api_token: string, default_model: string|null} $configuration */
        $configuration = json_decode($from['ap_configuration'], true, 512, \JSON_THROW_ON_ERROR);
        Assert::keyExists($configuration, 'endpoint_url');
        Assert::keyExists($configuration, 'api_token');

        return new AiProvider(
            id: new AiProviderId((int) $from['ap_id']),
            name: new AiProviderName($from['ap_name']),
            type: AiProviderTypeEnum::from($from['ap_type']),
            endpointUrl: new EndpointUrl($configuration['endpoint_url']),
            apiToken: new ApiToken($configuration['api_token']),
            defaultModel: isset($configuration['default_model'])
                ? new ModelName($configuration['default_model'])
                : null,
            description: $from['ap_description'] !== null && $from['ap_description'] !== ''
                ? new AiProviderDescription($from['ap_description'])
                : null,
            isDefault: (bool) $from['ap_is_default'],
        );
    }
}
