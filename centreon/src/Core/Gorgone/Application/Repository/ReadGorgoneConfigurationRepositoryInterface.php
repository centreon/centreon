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

namespace Core\Gorgone\Application\Repository;

interface ReadGorgoneConfigurationRepositoryInterface
{
    /**
     * Find the generated configuration files of a node for one target.
     *
     * @param int $nodeId
     * @param string $target one of the GorgoneCommandService::TARGET_* constants
     *
     * @throws \Throwable
     *
     * @return array<string, string> content of the regular files, indexed by their name relative to the target directory
     */
    public function findFiles(int $nodeId, string $target): array;
}
