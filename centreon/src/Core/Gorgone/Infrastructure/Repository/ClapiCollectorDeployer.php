<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\Gorgone\Infrastructure\Repository;

use CentreonClapi\CentreonConfigPoller;
use Core\Gorgone\Application\Repository\CollectorDeployerInterface;
use Pimple\Container;

/**
 * Exports a poller with the code of CLAPI POLLERGENERATE, CFGMOVE and POLLERRESTART.
 */
final class ClapiCollectorDeployer implements CollectorDeployerInterface
{
    /** Name the generation is logged under. */
    private const GENERATED_BY = 'collector-enrolment';

    public function __construct(private readonly Container $legacyContainer)
    {
    }

    /**
     * @inheritDoc
     */
    public function deploy(int $pollerId): array
    {
        require_once _CENTREON_PATH_ . 'www/class/centreon-clapi/centreon.Config.Poller.class.php';
        ob_start();
        try {
            $poller = new CentreonConfigPoller(_CENTREON_PATH_, $this->legacyContainer);
            $succeeded = $poller->pollerGenerate($pollerId, self::GENERATED_BY, '') === 0
                && $poller->cfgMove($pollerId) === 0
                && $poller->pollerRestart($pollerId) === 0;
        } catch (\Throwable $ex) {
            $succeeded = false;
            echo $ex->getMessage(), PHP_EOL;
        } finally {
            $output = (string) ob_get_clean();
        }

        return [$succeeded, $output];
    }
}
