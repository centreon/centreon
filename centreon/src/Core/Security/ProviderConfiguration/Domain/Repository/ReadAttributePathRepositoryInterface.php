<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\Security\ProviderConfiguration\Domain\Repository;

use Core\Security\ProviderConfiguration\Domain\Model\Configuration;

/**
 * Use this interface to implement the concrete repository class.
 * This interface is used on each fetchers to get information returned by the method getdata()
 * Data retrieval is, at the moment, processed only through http
 */
interface ReadAttributePathRepositoryInterface
{
    /**
     * @param string $url
     * @param string $token
     * @param Configuration $configuration
     * @return array
     */
    public function getData(string $url, string $token, Configuration $configuration): array;
}
