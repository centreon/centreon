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

namespace App\MonitoringConfiguration\Infrastructure;

use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Domain\Model\PollerToken;
use App\Shared\Domain\Logging\Attribute\Sensitive;

final readonly class PollerInstallationCommandFactory
{
    public function __construct(
        private Poller $poller,
        private PollerToken $pollerToken,
        #[Sensitive] private string $appSecret,
        #[Sensitive] private string $salt,
        private string $centralBaseUrl,
        private bool $isCloudPlatform = false,
    ) {
    }

    public function generate(): string
    {
        // Only `name` is escaped with escapeshellarg(): it is the sole free-form,
        // user-provided value. The other parameters are controlled and cannot carry
        // shell metacharacters: pollerToken name+value are hex (bin2hex), uid is an int,
        // pollerType is an enum, appSecret/salt are engine-generated, and centralBaseUrl
        // is assembled from the current HTTP request.
        $command = sprintf(
            'curl -fsSL %s/poller/install.sh | bash -s -- --poller_token %s:%s --uid %s --name %s --type %s --central_url %s --appsecret %s --salt %s',
            $this->centralBaseUrl,
            $this->pollerToken->name,
            $this->pollerToken->value,
            $this->poller->uid->value,
            escapeshellarg($this->poller->name->value),
            $this->poller->pollerType->value,
            $this->centralBaseUrl,
            $this->appSecret,
            $this->salt,
        );

        if ($this->isCloudPlatform) {
            $command .= ' --cloud';
        }

        return $command;
    }
}
