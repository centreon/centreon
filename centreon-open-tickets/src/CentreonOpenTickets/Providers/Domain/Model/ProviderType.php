<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace CentreonOpenTickets\Providers\Domain\Model;

enum ProviderType: int
{
    case Mail = 1;
    case Glpi = 2;
    case Otrs = 3;
    case Simple = 4;
    case BmcItsm = 5;
    case Serena = 6;
    case BmcFootprints11 = 7;
    case EasyvistaSoap = 8;
    case ServiceNow = 9;
    case Jira = 10;
    case GlpiRestApi = 11;
    case RequestTracker2 = 12;
    case Itop = 13;
    case EasyVistaRest = 14;
}
