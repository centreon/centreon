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

namespace App\Shared\Domain\Aggregate;

use App\Shared\Domain\VaultInterface;

/**
 * Marks an aggregate that may own a vault entry: deleting it must also purge that entry, or the
 * secret lingers in the vault forever with nothing left in Centreon referencing it.
 *
 * Unlike {@see AclScopedInterface}/{@see PollerScopedInterface}, which are pure markers, this
 * declares a real method: the generic vault-purge caller needs to actually resolve the entry's
 * UUID, not just detect that the aggregate is vault-eligible. Resolution is pure parsing (checking
 * whether a stored value is a `secret::` path) — no vault I/O — so it is safe to call from inside
 * the deleting transaction; only the actual purge call must wait until after commit.
 */
interface VaultScopedInterface
{
    /**
     * The UUID of this aggregate's vault entry, if it has one, or null when none of its
     * vault-eligible fields currently hold a `secret::` reference.
     */
    public function getVaultUuid(VaultInterface $vault): ?string;
}
