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

namespace App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration;

/**
 * The `cfg_centreonbroker_info.config_key` names shared across layers: the factory that
 * builds the default flows, the persistence transformer that derives the meta rows, the
 * central-token read query, and the vault key suffix. Kept in one place so the write and
 * read sides can never silently disagree on a key spelling.
 */
final class BrokerConfigKey
{
    /** The stream kind discriminator (`cb_type.name`); a meta row derived at persistence. */
    public const TYPE = 'type';

    /** The UI block identifier `{tagId}_{typeId}`; a meta row derived at persistence. */
    public const BLOCK_ID = 'blockId';

    /** The flow's display name. */
    public const NAME = 'name';

    /** The BBDO token shared between a bbdo_server input and its bbdo_client output. */
    public const AUTHORIZATION = 'authorization';
}
