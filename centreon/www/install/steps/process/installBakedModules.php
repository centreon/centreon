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

/*
 * Non-interactive equivalent of clicking "Install" on each of these modules
 * from the "Manage Modules" page — same mechanism, same
 * CentreonModuleService, no UI needed. These 4 modules are baked into the
 * image at build time (T0 decision), their files already present under
 * www/modules/<name>/ by the time this runs; this step only handles the
 * one-time DB registration (module_informations row, module-specific
 * tables, menu entries) each module's own sql/install.sql + php/install.php
 * does.
 */

session_start();
require_once __DIR__ . '/../../../../bootstrap.php';

$moduleService = $dependencyInjector[CentreonModule\ServiceProvider::CENTREON_MODULE];

// Dependency order matters: pp-manager needs license-manager already
// installed, autodiscovery needs both license-manager and pp-manager,
// it-edition-extensions needs license-manager (each module's own Depends:
// field, confirmed by inspecting the real .deb packages).
//
// Note the autodiscovery id: the Debian PACKAGE is named
// "centreon-auto-discovery-server" (with the extra hyphen — that's the
// name used for the apt path in the Dockerfile), but the actual
// www/modules/<id>/ directory inside it, which is what
// CentreonModuleService keys on, is "centreon-autodiscovery-server" (no
// hyphen there) — confirmed by listing the extracted image directly, not
// assumed from the package name.
$toInstall = [
    'centreon-license-manager',
    'centreon-pp-manager',
    'centreon-autodiscovery-server',
    'centreon-it-edition-extensions',
];

// One module's failure must not take the whole container down —
// getDetail()/install() are wrapped per module, not left to a single
// uncaught exception that would abort this script (and, under 12-install.sh's
// set -eu, the entire fpm startup) for every module after the first
// failure too. Kept as defensive resilience even now that all 4 modules
// install cleanly — a real install-time failure shouldn't be fatal to the
// container either.
//
// Historical note: centreon-autodiscovery-server/centreon-it-edition-
// extensions used to fatal here with "Cannot find license directory"
// (CentreonLicense\ServiceProvider, SourceGuardian-encoded, unreadable —
// several missing-directory guesses tried and ruled out empirically). Real
// cause, confirmed against a live production install: the missing
// directory was /etc/centreon/license.d/ (note the ".d", not the plain
// "license/" paths tried first) — the fpm Dockerfile now creates it
// (centreon:centreon, 775, same block as /var/lib/centreon).
foreach ($toInstall as $moduleId) {
    try {
        $module = $moduleService->getDetail($moduleId, 'module');
        if ($module !== null && $module->isInstalled()) {
            echo "{$moduleId} already installed, skipping.\n";
            continue;
        }
        echo "Installing {$moduleId}...\n";
        $moduleService->install($moduleId, 'module');
    } catch (Throwable $e) {
        fwrite(STDERR, "Failed to install {$moduleId}: {$e->getMessage()}\n");
    }
}
