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

namespace Core\AgentConfiguration\Application\Validation;

use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\UseCase\AddAgentConfiguration\AddAgentConfigurationRequest;
use Core\AgentConfiguration\Application\UseCase\UpdateAgentConfiguration\UpdateAgentConfigurationRequest;
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\TelegrafConfigurationParameters;
use Core\AgentConfiguration\Domain\Model\Type;

/**
 * @phpstan-import-type _TelegrafParameters from TelegrafConfigurationParameters
 */
class TelegrafValidator implements TypeValidatorInterface
{
    /**
     * @inheritDoc
     */
    public function isValidFor(Type $type): bool
    {
        return Type::TELEGRAF === $type;
    }

    /**
     * @inheritDoc
     */
    public function validateParametersOrFail(AddAgentConfigurationRequest|UpdateAgentConfigurationRequest $request): void
    {
        /** @var _TelegrafParameters $configuration */
        $configuration = $request->configuration;
        foreach ($configuration as $key => $value) {
            if (
                (
                    str_ends_with($key, '_certificate')
                    || str_ends_with($key, '_key')
                )
                && 1 === preg_match('/\.\/|\.\.\/|\.cert$|\.crt$|\.key$/', (string) $value)
            ) {
                throw AgentConfigurationException::invalidFilename("configuration.{$key}", (string) $value);
            }
        }
    }
}
