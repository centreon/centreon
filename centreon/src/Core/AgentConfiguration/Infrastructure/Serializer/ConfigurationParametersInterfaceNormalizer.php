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

use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\CmaConfigurationParameters;
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\TelegrafConfigurationParameters;
use Core\AgentConfiguration\Domain\Model\ConfigurationParametersInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @phpstan-import-type _TelegrafParameters from TelegrafConfigurationParameters
 * @phpstan-import-type _CmaParameters from CmaConfigurationParameters
 */
class ConfigurationParametersInterfaceNormalizer implements NormalizerInterface
{
    /**
     * {@inheritDoc}
     *
     * @param ConfigurationParametersInterface $object
     * @param string|null $format
     * @param array<string, mixed> $context
     *
     * @return _TelegrafParameters|_CmaParameters|null
     */
    public function normalize(
        mixed $object,
        ?string $format = null,
        array $context = []
    ): float|int|bool|array|string|null {
        /** @var array{groups: string[]} $context */
        if (in_array('AgentConfiguration:Read', $context['groups'], true)) {
            /** @var _TelegrafParameters|_CmaParameters $data */
            $data = $object->getData();
        }

        return $data ?? null;
    }

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null): bool
    {
        return $data instanceof ConfigurationParametersInterface;
    }
}