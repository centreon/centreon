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

namespace Adaptation\Log\Channel;

use Adaptation\Log\Exception\LoggerException;

/**
 * A module-owned logging channel on the unified Monolog pipeline.
 *
 * Lets a module (which cannot extend the closed core {@see \Adaptation\Log\Enum\LogChannelEnum})
 * emit on its own dedicated file while still getting masking, exception formatting,
 * request context and the cross-channel uid. The historical log file name is
 * preserved verbatim (e.g. `license-manager.log`, `autodiscovery_job.log`): no
 * `APP_ENV` prefix is added, because external consumers (ops runbooks, SIEM parsers)
 * watch these files by that exact path.
 */
final readonly class ModuleLogChannel implements LogChannelInterface
{
    /**
     * Slug accepted as a channel name: lowercase alphanumerics, separated by `-` or
     * `_`, no leading/trailing separator. Strict by design — the value becomes a file
     * name component, so anything that could escape the log directory is rejected.
     */
    private const NAME_PATTERN = '/^[a-z0-9]([a-z0-9_-]*[a-z0-9])?$/';

    /**
     * @throws LoggerException when $name is not a valid channel slug
     */
    public function __construct(private string $name)
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw LoggerException::invalidModuleChannel($name);
        }
    }

    /**
     * Builds a channel from a historical log file name (e.g. `license-manager.log`),
     * stripping a trailing `.log`. Lets legacy callers that pass a file name keep
     * working while routing onto the unified pipeline.
     *
     * @throws LoggerException when the derived name is not a valid channel slug
     */
    public static function fromLogFileName(string $logFileName): self
    {
        return new self((string) preg_replace('/\.log$/', '', $logFileName));
    }

    public function getChannelName(): string
    {
        return $this->name;
    }

    public function getLogFileName(string $appEnv): string
    {
        return $this->name . '.log';
    }
}
