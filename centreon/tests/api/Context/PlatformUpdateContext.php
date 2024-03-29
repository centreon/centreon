<?php

/*
 * Copyright 2005 - 2020 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Centreon\Test\Api\Context;

use Centreon\Test\Behat\Api\Context\ApiContext;

class PlatformUpdateContext extends ApiContext
{
    /**
     * Create an update file
     *
     * @Given an update is available
     */
    public function anUpdateIsAvailable()
    {
        $this->getContainer()->execute(
            'mkdir -p /usr/share/centreon/www/install/php',
            'web'
        );
        $this->getContainer()->execute(
            "sh -c 'echo \"<?php\" > /usr/share/centreon/www/install/php/Update-99.99.99.php'",
            'web'
        );
        $this->getContainer()->execute(
            'chmod -R 777 /usr/share/centreon/www/install',
            'web'
        );
    }
}
