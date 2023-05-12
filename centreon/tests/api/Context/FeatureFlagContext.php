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
use Core\Common\Infrastructure\FeatureFlags;

/**
 * @see FeatureFlags
 */
class FeatureFlagContext extends ApiContext
{
    /**
     * Set a feature flag bitmask.
     *
     * @Given /^a feature flag "(\S+)" of bitmask (\d+)$/
     *
     * @param string $feature
     * @param int $bitmask
     */
    public function aFeatureFlagValue(string $feature, int $bitmask): void
    {
        $this->setFeatureFlagInsideTheContainer($feature, $bitmask);
    }

    /**
     * We modify the file `config/features.json` to add the feature flag.
     *
     * @param string $feature
     * @param int $bitmask
     */
    protected function setFeatureFlagInsideTheContainer(string $feature, int $bitmask): void
    {
        $centreonDir = '/usr/share/centreon';
        $featureExported = var_export([$feature => $bitmask], true);

        $phpScript = <<<PHP
            \$fic = '{$centreonDir}/config/features.json';
            \$a = !is_file(\$fic) ? [] : (json_decode(file_get_contents(\$fic), true) ?: []);
            \$a = array_merge(\$a, {$featureExported});
            file_put_contents(\$fic, json_encode(\$a, JSON_PRETTY_PRINT) ?: '{}');
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
