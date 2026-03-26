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

namespace Centreon\Test\Mock\DependencyInjector;

use Centreon\Test\Mock\CentreonDB;
use Pimple\Container;

class ConfigurationDBProvider implements ServiceProviderInterface
{
    private CentreonDB $db;

    public function __construct(CentreonDB $db)
    {
        $this->db = $db;
    }

    public function register(Container $container): void
    {
        $container['configuration_db'] = $this->db;
    }

    public function terminate(Container $container): void
    {
        $container['configuration_db'] = null;
    }
}
