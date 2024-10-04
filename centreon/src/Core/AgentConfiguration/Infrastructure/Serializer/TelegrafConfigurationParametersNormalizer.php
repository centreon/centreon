<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\AgentConfiguration\Infrastructure\Serializer;

use Core\AgentConfiguration\Application\UseCase\FindAgentConfiguration\FindAgentConfigurationResponse;
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\TelegrafConfigurationParameters;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class TelegrafConfigurationParametersNormalizer implements NormalizerInterface
{
    /**
     * @param ObjectNormalizer $normalizer
     */
    public function __construct(
        private readonly ObjectNormalizer $normalizer,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @param TelegrafConfigurationParameters $object
     * @param string|null $format
     * @param array<string, mixed> $context
     *
     * @return array<string, int|string>
     */
    public function normalize(
        mixed $object,
        ?string $format = null,
        array $context = []
    ): float|int|bool|array|string|null {
        if (in_array('AgentConfiguration:Read', $context['groups'], true)) {
            $data = $object->getData();
        }

        return $data ?? null;
    }

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof TelegrafConfigurationParameters;
    }
}