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

final readonly class PollerInstallationCommandFactory
{
    //TODO to update with final path when available
    private const INSTALL_SCRIPT_PATH = 'https://raw.githubusercontent.com/centreon/centreon-collect/refs/tags/%s-latest/poller/installer/install.sh';

    public function __construct(
        private Poller $poller,
        private string $pollerToken,
        private string $appSecret,
        private string $salt,
        private string $centralUrl,
        private string $platformVersion,
    ) {
    }

    public function generate(): string
    {
        return sprintf(
            'curl -fsSL ' . self::INSTALL_SCRIPT_PATH . ' | bash -s -- --poller_token %s --uid %s --name %s --type %s --central_url %s --appsecret %s --salt %s',
            $this->platformVersion,
            $this->pollerToken,
            $this->poller->uid->value,
            $this->poller->name->value,
            $this->poller->pollerType->value,
            $this->centralUrl,
            $this->appSecret,
            $this->salt,
        );
    }
}
