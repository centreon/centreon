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

namespace App\MonitoringConfiguration\Application\Service;

use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\ModuleRepository;
use App\Shared\Domain\License\HostLicenseLimitInterface;
use App\Shared\Domain\License\LicenseCheckInterface;

/**
 * Legacy CentreonHost::isAllowed(). The license is read through the license manager module, which
 * provides both license services: without them (module absent) the limit is taken as reached, as
 * legacy does.
 */
final readonly class HostLicenseLimit
{
    /** The license product the host limit is read from. */
    public const LICENSE_PRODUCT = 'epp';
    private const LICENSE_MANAGER_MODULE = 'centreon-license-manager';

    public function __construct(
        private ModuleRepository $moduleRepository,
        private HostRepository $hostRepository,
        private ?LicenseCheckInterface $licenseCheck = null,
        private ?HostLicenseLimitInterface $hostLicenseLimit = null,
    ) {
    }

    /**
     * Reached unless the license is valid and allows more hosts than the platform has, whether they
     * are enabled or not.
     */
    public function isReached(): bool
    {
        if (
            ! $this->licenseCheck instanceof LicenseCheckInterface
            || ! $this->hostLicenseLimit instanceof HostLicenseLimitInterface
            || ! $this->moduleRepository->isInstalled(self::LICENSE_MANAGER_MODULE)
            || ! $this->licenseCheck->isValid(self::LICENSE_PRODUCT)
        ) {
            return true;
        }

        $maxHosts = $this->hostLicenseLimit->getMaxHosts(self::LICENSE_PRODUCT);

        return $maxHosts !== null && $maxHosts <= $this->hostRepository->countAll();
    }
}
