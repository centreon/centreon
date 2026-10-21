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

namespace App\Upgrade\Domain;

/**
 * Shared by the platform update and the upgrade wizard, which announces it.
 */
final class UpgradeTargetVersion
{
    /**
     * @param string $platformVersion the version of the installed code
     * @param string $legacyVersion the version reached by the legacy update scripts
     * @param bool $nextUpdatePending whether Update-next.php exists, as on a development build: recording the
     *                                coming release would skip it once renamed to it
     */
    public static function resolve(string $platformVersion, string $legacyVersion, bool $nextUpdatePending): string
    {
        // A legacy script may also be ahead, as a hotfix.
        return ! $nextUpdatePending && version_compare($platformVersion, $legacyVersion, '>') ? $platformVersion : $legacyVersion;
    }
}
