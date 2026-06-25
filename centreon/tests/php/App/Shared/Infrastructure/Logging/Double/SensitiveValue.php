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

namespace Tests\App\Shared\Infrastructure\Logging\Double;

use App\Shared\Domain\Logging\Attribute\Sensitive;

/**
 * A private, getter-less property (here also `#[Sensitive]`). Pins that
 * internal state with no public accessor is not logged at all — matching the
 * standard object-normalizer exposure — so a secret in such a field cannot
 * leak into the payload.
 */
final class SensitiveValue
{
    public string $label = 'visible';

    #[Sensitive]
    private string $vaultSalt = 'top-secret';

    public function reveal(): string
    {
        return $this->vaultSalt;
    }
}
