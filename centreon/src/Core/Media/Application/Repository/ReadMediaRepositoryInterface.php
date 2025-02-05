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

declare(strict_types = 1);

namespace Core\Media\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Media\Domain\Model\Media;

interface ReadMediaRepositoryInterface
{
    /**
     * Indicates whether the media exists using its path.
     *
     * @param string $path (ex: /logos/centreon_logo.png)
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByPath(string $path): bool;

    /**
     * @throws \Throwable
     *
     * @return \Traversable<int, Media>&\Countable
     */
    public function findAll(): \Traversable&\Countable;

    /**
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Throwable
     *
     * @return \Traversable<int, Media>
     */
    public function findByRequestParameters(RequestParametersInterface $requestParameters): \Traversable;

    /**
     * @param int $mediaId
     *
     * @throws \Throwable
     *
     * @return Media|null
     */
    public function findById(int $mediaId): ?Media;
}
