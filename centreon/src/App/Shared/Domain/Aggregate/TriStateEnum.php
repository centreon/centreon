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

/**
 * Three-valued flag for a monitoring directive that can be explicitly on, explicitly off, or left
 * to inherit its default (template chain, then engine default).
 *
 * Modeled after the well-known TriState pattern (e.g. Microsoft.VisualBasic.TriState:
 * True / False / UseDefault). Backing values are the API-contract strings so a typed property
 * (de)serializes natively; the mapping to the host table's `enum('0','1','2')` columns lives in
 * the DBAL layer. `UseDefault` means "do not write the directive" — it is resolved by the
 * template chain / engine default (the "Default" business rule of the host configuration).
 */
enum TriStateEnum: string
{
    case False = 'false';
    case True = 'true';
    case UseDefault = 'use_default';
}
