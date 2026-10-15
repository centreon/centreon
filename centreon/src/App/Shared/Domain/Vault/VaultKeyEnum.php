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
 * Catalogue of the FIXED, well-known secret keys stored in the vault.
 *
 * Secrets whose key is dynamic (e.g. user-defined password-type macro names) are NOT listed
 * here; they flow as plain strings. These values MUST match the key constants in Core
 * `Core\Security\Vault\Domain\Model\VaultConfiguration`. App cannot import Core (deptrac), so
 * the literals are duplicated here and guarded by a unit test.
 */
enum VaultKeyEnum: string
{
    case HostSnmpCommunity = '_HOSTSNMPCOMMUNITY';
    case OpenIdClientId = '_OPENID_CLIENT_ID';
    case OpenIdClientSecret = '_OPENID_CLIENT_SECRET';
    case DatabaseUsername = '_DBUSERNAME';
    case DatabasePassword = '_DBPASSWORD';
    case KnowledgeBasePassword = '_KBPASSWORD';
    case GorgonePassword = '_GORGONE_PASSWORD';
}
