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

class CloudPlatformContext extends FeatureFlagContext
{
    /**
     * Launch Centreon Web container with the environment variable IS_CLOUD_PLATFORM=1.
     *
     * @Given a running cloud platform instance of Centreon Web API
     */
    public function aRunningCloudPlatformInstanceOfCentreonApi(): void
    {
        $this->aRunningInstanceOfCentreonApi();
        $this->setEnvironmentVariableInsideTheContainer(['IS_CLOUD_PLATFORM' => true]);
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
        $envVarsExported = var_export($envars, true);

        $phpScript = <<<PHP
            require_once '{$centreonDir}/vendor/autoload.php';
            require_once '{$centreonDir}/config/centreon.config.php';

            use Symfony\Component\Dotenv\Dotenv;

            \$env = new \Utility\EnvironmentFileManager(_CENTREON_PATH_);
            \$env->load();
            foreach({$envVarsExported} as \$envVarKey => \$envVarValue) {
                \$env->add(\$envVarKey, \$envVarValue);
            }
            \$env->add('IS_CLOUD_PLATFORM', true);
            \$env->save();

            (new Dotenv())->bootEnv('{$centreonDir}/.env', 'dev', ['test'], true);
            PHP;

        // We MUST remove the linefeed (\n) to be a valid oneline command.
        $phpOneline = preg_replace('!\s*\n\s*!', '', $phpScript);

        // Do not forget to escape special chars !
        $this->container->execute(
            'php -r ' . escapeshellarg($phpOneline) . ' 2>&1',
            $this->webService
        );

        $result = $this->container->execute(
            'ps aux | grep -E "[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx" | grep -v root | head -1 | cut -d\  -f1',
            $this->webService
        );
        if (!isset($result['output'])) {
            throw new \Exception('Cannot get apache user');
        }
        $apacheUser = $result['output'];

        // Reload symfony cache to use updated environment variables
        $this->container->execute(
            'su ' . $apacheUser . ' -s /bin/bash -c "/usr/share/centreon/bin/console cache:clear"',
            $this->webService
        );
    }
}
