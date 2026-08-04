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
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerUid;
use App\MonitoringConfiguration\Domain\Model\PollerToken;
use App\Shared\Domain\Logging\Attribute\Sensitive;

final readonly class PollerInstallationCommandFactory
{
    public function __construct(
        private PollerUid $pollerUid,
        private PollerName $pollerName,
        private PollerTypeEnum $pollerType,
        private PollerToken $pollerToken,
        #[Sensitive] private string $appSecret,
        #[Sensitive] private string $salt,
        private bool $isCloudPlatform = false,
        private string $centralUrl = '<CENTRAL_URL>',
    ) {
    }

    /**
     * Preferred entry point when a full Poller aggregate is at hand: it guarantees
     * uid, name and type all come from the same poller.
     */
    public static function fromPoller(
        Poller $poller,
        PollerToken $pollerToken,
        string $appSecret,
        string $salt,
        bool $isCloudPlatform = false,
        string $centralUrl = '<CENTRAL_URL>',
    ): self {
        return new self(
            $poller->uid,
            $poller->name,
            $poller->pollerType,
            $pollerToken,
            $appSecret,
            $salt,
            $isCloudPlatform,
            $centralUrl,
        );
    }

    public function generate(): string
    {
        // Only `name` is escaped with escapeshellarg(): it is the sole free-form,
        // user-provided value. The other parameters are controlled and cannot carry
        // shell metacharacters: pollerToken name+value are hex (bin2hex), uid is an int,
        // pollerType is an enum, appSecret/salt are engine-generated, and centralUrl comes from config.
        $command = sprintf(
            'curl -fsSL https://%s/poller/install.sh | bash -s -- --poller_token %s:%s --uid %s --name %s --type %s --central_url %s --appsecret %s --salt %s',
            $this->centralUrl,
            $this->pollerToken->name,
            $this->pollerToken->value,
            $this->pollerUid->value,
            escapeshellarg($this->pollerName->value),
            $this->pollerType->value,
            $this->centralUrl,
            $this->appSecret,
            $this->salt,
        );

        if ($this->isCloudPlatform) {
            $command .= ' --cloud';
        }

        return $command;
    }
}
