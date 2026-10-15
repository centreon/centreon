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

namespace App\Shared\Domain\Vault;

/**
 * Catalogue of the vault custom sub-paths (the vault "topology").
 *
 * These values MUST stay in sync with the allow-list Core enforces in
 * `Core\Common\Infrastructure\Repository\AbstractVaultRepository`. App cannot import Core
 * (deptrac), so the literals are duplicated here and guarded by a unit test. A value absent
 * from Core's allow-list makes the underlying vault write throw at runtime.
 */
enum VaultPathEnum: string
{
    case MonitoringHosts = 'monitoring/hosts';
    case MonitoringServices = 'monitoring/services';
    case MonitoringPollerMacros = 'monitoring/pollerMacros';
    case KnowledgeBase = 'configuration/knowledge_base';
    case OpenId = 'configuration/openid';
    case Gorgone = 'configuration/gorgone';
    case Broker = 'configuration/broker';
    case AdditionalConnectorConfigurations = 'configuration/additionalConnectorConfigurations';
    case Database = 'database';
}
