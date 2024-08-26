<?php declare(strict_types=1);

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

namespace CentreonLegacy\Core\Module;

use CentreonLegacy\ServiceProvider;
use Psr\Container\ContainerInterface;

/**
 * License service provide information about module licenses.
 */
class License extends Module
{
    /** @var ContainerInterface */
    protected $services;

    /**
     * Construct.
     *
     * @param ContainerInterface $services
     */
    public function __construct(ContainerInterface $services)
    {
        $this->services = $services;
    }

    /**
     * Get license expiration date.
     *
     * @param string $module
     *
     * @return string
     */
    public function getLicenseExpiration($module): ?string
    {
        $healthcheck = $this->services->get(ServiceProvider::CENTREON_LEGACY_MODULE_HEALTHCHECK);

        try {
            $healthcheck->check($module);
        } catch (\Exception $ex) {
        }

        if ($expiration = $healthcheck->getLicenseExpiration()) {
            return $expiration->format(\DateTime::ISO8601);
        }

        return null;
    }
}
