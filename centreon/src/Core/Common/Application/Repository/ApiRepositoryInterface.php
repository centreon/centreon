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

namespace Core\Common\Application\Repository;

use Core\Proxy\Domain\Model\Proxy;

interface ApiRepositoryInterface
{
    /**
     * @param string $token
     */
    public function setAuthenticationToken(string $token): void;

    /**
     * @param int $maxItemsByRequest Number of items that can be requested per call
     */
    public function setMaxItemsByRequest(int $maxItemsByRequest): void;

    /**
     * @see Proxy::__toString()
     *
     * @param string $proxy String representation of proxy url
     */
    public function setProxy(string $proxy): void;

    /**
     * Define API call timeout.
     *
     * @param int $timeout In seconds
     */
    public function setTimeout(int $timeout): void;

    /**
     * @param string $url
     */
    public function setUrl(string $url): void;
}
