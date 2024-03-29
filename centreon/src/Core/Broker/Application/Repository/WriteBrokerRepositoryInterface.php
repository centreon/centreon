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

namespace Core\Broker\Application\Repository;

interface WriteBrokerRepositoryInterface
{
    /**
     * Create a file with defined content.
     *
     * @param string $filename
     * @param string $content
     *
     * @throws \Throwable
     */
    public function create(string $filename, string $content): void;

    /**
     * Delete a file.
     *
     * @param string $filename
     *
     * @throws \Throwable
     */
    public function delete(string $filename): void;
}
