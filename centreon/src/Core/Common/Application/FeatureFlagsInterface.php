<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Common\Application;

/**
 * This class is a facade to the feature flag system which consists in a simple JSON file saved in this project.
 *
 * This feature flags system is static by purpose.
 *
 * Each value is a flags bitmask depending on the current platform (On-Prem / Cloud).
 */
interface FeatureFlagsInterface
{
    /**
     * Simple public exposition of the internal flag.
     *
     * @return bool
     */
    public function isCloudPlatform(): bool;

    /**
     * Return all configured flags with their value.
     *
     * @return array<string, bool>
     */
    public function getAll(): array;
}
