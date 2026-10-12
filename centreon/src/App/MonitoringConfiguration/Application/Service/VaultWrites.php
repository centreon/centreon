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

/**
 * Whether something was written to the vault during an update. The vault is outside the transaction of the
 * database: when the update fails after a write, the caller needs to know that the vault may now diverge
 * from the host it still stores.
 */
final class VaultWrites
{
    private bool $written = false;

    public function record(): void
    {
        $this->written = true;
    }

    public function happened(): bool
    {
        return $this->written;
    }
}
