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

namespace Centreon\Test\Api\Context;

use Centreon\Test\Behat\Api\Context\ApiContext;

class CloudPlatformContext extends ApiContext
{
    /**
     * Launch Centreon Web container with the environment variable IS_CLOUD_PLATFORM=1.
     *
     * @Given a running cloud platform instance of Centreon Web API
     */
    public function aRunningCloudPlatformInstanceOfCentreonApi(): void
    {
        $this->aRunningInstanceOfCentreonApi();
        $this->setEnvironmentVariableInsideTheContainer(['IS_CLOUD_PLATFORM' => 1]);
    }

    /**
     * We modify the file `.env.local.php` to add the environment variables.
     * If it does not exist, we build the php array from the `.env` file which SHOULD exist.
     *
     * For that, we must run a php from inside the container,
     * and we build a one line php command from a minimalist script.
     *
     * @param array<string, string|int|float|bool> $envars
     *
     * @throws \Exception
     */
    protected function setEnvironmentVariableInsideTheContainer(array $envars): void
    {
        $centreonDir = '/usr/share/centreon';
        $envvarsExported = var_export($envars, true);

        $phpScript = <<<PHP
            \$php = '{$centreonDir}/.env.local.php';
            \$env = '{$centreonDir}/.env';
            if (is_file(\$php)){
                \$a = require \$php;
            }else{
                is_file(\$env) ?: throw new Exception("\$env Not Found.");
                \$a = [];
                foreach (file(\$env) as \$l){
                    if (preg_match('!^([^#]+?)=(.+)$!', trim(\$l), \$m)){
                        \$a[\$m[1]] = \$m[2];
                    }
                }
            }
            \$a = array_merge(\$a, {$envvarsExported});
            file_put_contents(\$php, "<?php return ".var_export(\$a,true).";");
            PHP;

        // We MUST remove the linefeed (\n) to be a valid oneline command.
        $phpOneline = preg_replace('!\s*\n\s*!', '', $phpScript);

        // Do not forget to escape special chars !
        $this->container->execute(
            'php -r ' . escapeshellarg($phpOneline) . ' 2>&1',
            $this->webService
        );
    }
}
