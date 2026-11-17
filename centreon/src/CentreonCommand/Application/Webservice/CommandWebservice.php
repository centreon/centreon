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

namespace CentreonCommand\Application\Webservice;

use Centreon\Infrastructure\Webservice;
use Centreon\ServiceProvider;
use CentreonCommand\Application\Serializer;
use CentreonCommand\Domain\Entity\Command;
use CentreonCommand\Domain\Repository;

class CommandWebservice extends Webservice\WebServiceAbstract implements Webservice\WebserviceAutorizeRestApiInterface
{
    use Webservice\DependenciesTrait;

    /**
     * {@inheritDoc}
     */
    public static function getName(): string
    {
        return 'centreon_command';
    }

    /**
     * {@inheritDoc}
     *
     * @return array<ServiceProvider::CENTREON_PAGINATION>
     */
    public static function dependencies(): array
    {
        return [
            ServiceProvider::CENTREON_PAGINATION,
        ];
    }

    /**
     * Get list of commands
     *
     * @throws \RestBadRequestException
     *
     * @return \Centreon\Application\DataRepresenter\Response
     */
    public function getList()
    {
        // extract post payload
        $request = $this->query();

        $limit = isset($request['limit']) ? (int) $request['limit'] : null;
        $offset = isset($request['offset']) ? (int) $request['offset'] : null;

        $filters = [];

        if (isset($request['search']) && $request['search']) {
            $filters['search'] = $request['search'];
        }

        if (isset($request['searchByIds']) && $request['searchByIds']) {
            $filters['ids'] = explode(',', $request['searchByIds']);
        }

        if (isset($request['type']) && $request['type']) {
            $typeId = Command::getTypeIdFromName($request['type']);

            if ($typeId !== null) {
                $filters['type'] = $typeId;
            }

            unset($typeId);
        }

        $pagination = $this->services->get(ServiceProvider::CENTREON_PAGINATION);
        $pagination->setRepository(Repository\CommandRepository::class);
        $pagination->setContext(Serializer\Command\ListContext::context());
        $pagination->setFilters($filters);
        $pagination->setLimit($limit);
        $pagination->setOffset($offset);

        return $pagination->getResponse();
    }
}
