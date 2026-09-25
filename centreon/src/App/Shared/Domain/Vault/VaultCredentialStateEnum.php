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
 * Lifecycle intent carried by a single {@see VaultCredentials} entry on update.
 *
 * The three states are what let an update consumer (PUT/PATCH) tell apart a value the caller
 * left untouched, a new value to store, and a value the caller explicitly emptied:
 *
 * - {@see self::Set}: a new plaintext value the caller provided; it must be written to the vault.
 * - {@see self::Cleared}: the caller explicitly emptied the field; its key must be deleted from the
 *   vault entry.
 * - {@see self::Unchanged}: the caller did not touch the field; its stored value (a plaintext value,
 *   or a `secret::` reference resubmitted as-is) is carried through untouched.
 *
 * A field the caller did not provide at all (e.g. absent from a PATCH payload) is simply not present
 * in the {@see VaultCredentials} map; absence, not a state, models "not provided".
 */
enum VaultCredentialStateEnum
{
    case Set;
    case Cleared;
    case Unchanged;
}
