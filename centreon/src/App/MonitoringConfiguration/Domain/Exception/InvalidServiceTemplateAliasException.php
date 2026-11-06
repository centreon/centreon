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

namespace App\MonitoringConfiguration\Domain\Exception;

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;

/**
 * The stored alias of a service template cannot name the service deployed from it: a validation failure on
 * stored data (HTTP 422) that fails the whole deployment.
 */
final class InvalidServiceTemplateAliasException extends \RuntimeException
{
    private function __construct(int $templateId, string $reason)
    {
        parent::__construct(sprintf('The alias of service template #%d %s, no service was deployed.', $templateId, $reason));
    }

    public static function empty(int $templateId): self
    {
        return new self($templateId, 'is empty');
    }

    public static function tooLong(int $templateId): self
    {
        return new self($templateId, sprintf('is longer than %d characters', ServiceName::MAX_LENGTH));
    }

    /**
     * @param string $forbiddenCharacters the characters a name may not hold, listed in the message
     */
    public static function forbiddenCharacter(int $templateId, string $forbiddenCharacters): self
    {
        return new self(
            $templateId,
            sprintf('contains a forbidden character (%s)', implode(' ', mb_str_split($forbiddenCharacters))),
        );
    }

    public static function controlCharacter(int $templateId): self
    {
        return new self($templateId, 'contains a control character');
    }
}
