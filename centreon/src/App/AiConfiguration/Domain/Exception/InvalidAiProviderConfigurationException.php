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

namespace App\AiConfiguration\Domain\Exception;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;

/**
 * The AI provider configuration given by the user is not coherent (billing
 * fields that do not go together, an endpoint the provider type does not
 * accept, ...).
 */
final class InvalidAiProviderConfigurationException extends \InvalidArgumentException
{
    public static function currencyRequired(): self
    {
        return new self('A currency is required when prices or spend limits are set.');
    }

    public static function limitRequiresPrices(): self
    {
        return new self('A spend limit requires the default tier prices to be set.');
    }

    public static function fastPriceRequiresDefaultPrice(): self
    {
        return new self('The default tier prices are required when a fast tier price is set.');
    }

    public static function endpointRequired(AiProviderTypeEnum $type): self
    {
        return new self(sprintf("An endpoint URL is required for providers of type '%s'.", $type->value));
    }

    public static function apiTokenRequired(AiProviderTypeEnum $type): self
    {
        return new self(sprintf("An API token is required for providers of type '%s'.", $type->value));
    }

    public static function endpointNotAllowed(AiProviderTypeEnum $type): self
    {
        return new self(sprintf("Providers of type '%s' are reached at their official endpoint only.", $type->value));
    }

    public static function invalidEndpoint(\InvalidArgumentException $previous): self
    {
        return new self($previous->getMessage(), previous: $previous);
    }

    public static function metadataEndpoint(): self
    {
        return new self('The endpoint URL can not target a link-local address or a cloud instance metadata service.');
    }

    public static function invalidBedrockEndpoint(): self
    {
        return new self(
            'The endpoint URL of an Amazon Bedrock provider must be the Mantle endpoint of an AWS region '
            . '(https://bedrock-mantle.<region>.api.aws).'
        );
    }
}
