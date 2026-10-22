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

namespace App\Shared\Domain\License;

interface HostLicenseLimitInterface
{
    /**
     * The number of hosts the product's license allows, read from the license file
     * (`licensing.hosts`), null when it allows any number. Whether the license itself is valid
     * is {@see LicenseCheckInterface::isValid()}'s concern.
     */
    public function getMaxHosts(string $product): ?int;
}
