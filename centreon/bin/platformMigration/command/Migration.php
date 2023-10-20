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

namespace CloudMigration;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;

class Migration extends Command
{
    protected function configure(): void
    {
        $this->addArgument(
            'target-url',
            InputArgument::REQUIRED,
            "The target platform base url to connect to the API (ex: 'http://localhost/centreon')."
        );
        $this->addArgument(
            'target-token',
            InputArgument::REQUIRED,
            'The API token to connect to the target platform.'
        );
    }
}