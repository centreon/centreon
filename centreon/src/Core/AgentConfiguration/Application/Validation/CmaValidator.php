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
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\CmaConfigurationParameters;
use Core\AgentConfiguration\Domain\Model\Type;

/**
 * @phpstan-import-type _CmaParameters from CmaConfigurationParameters
 */
class CmaValidator implements TypeValidatorInterface
{
    /**
     * @inheritDoc
     */
    public function isValidFor(Type $type): bool
    {
        return Type::CMA === $type;
    }

    /**
     * @inheritDoc
     */
    public function validateParametersOrFail(AddAgentConfigurationRequest|UpdateAgentConfigurationRequest $request): void
    {
        /** @var _CmaParameters $configuration */
        $configuration = $request->configuration;
        foreach ($configuration as $key => $value) {
            if (
                (
                    str_ends_with($key, '_certificate')
                    || str_ends_with($key, '_key')
                )
                && (is_string($value) || is_null($value))
            ) {
                $this->validateFilename("configuration.{$key}", $value);
            }

            if ($key === 'hosts') {
                foreach ($value as $host) {
                    /** @var array{
                     *		address: string,
                     *		port: int,
                     *		certificate: string,
                     *		key: string
                     *	} $host
                     */
                    $this->validateFilename('configuration.hosts[].key', $host['key']);
                    $this->validateFilename('configuration.hosts[].certificate', $host['certificate']);
                }
            }
        }
    }

    /**
     * @param string $name
     * @param ?string $value
     *
     * @throws AgentConfigurationException
     */
    private function validateFilename(string $name, ?string $value): void
    {
        if (
            $value !== null
            && 1 === preg_match('/\.\/|\.\.\/|\.cert$|\.crt$|\.key$/', $value)
        ) {
            throw AgentConfigurationException::invalidFilename("configuration.{$name}", (string) $value);
        }
    }
}
