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

namespace Core\Notification\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Notification\Application\Repository\NotifiableResourceRequestProviderInterface;
use Core\Notification\Application\Repository\ReadNotifiableResourceRepositoryInterface as RepositoryInterface;

class DbReadNotifiableResourceRepository extends AbstractRepositoryRDB implements RepositoryInterface
{
    use LoggerTrait;

    /** @var NotifiableResourceRequestProviderInterface[] */
    private array $notifiableResourceRequestProviders;

    /**
     * @param DatabaseConnection $db
     * @param NotifiableResourceRequestProviderInterface[] $notifiableResourceRequestProviders
     */
    public function __construct(DatabaseConnection $db, iterable $notifiableResourceRequestProviders)
    {
        $this->db = $db;
        $requestProvidersAsArray = \is_array($notifiableResourceRequestProviders)
            ? $notifiableResourceRequestProviders
            : \iterator_to_array($notifiableResourceRequestProviders);
        if ($requestProvidersAsArray === []) {
            throw new \InvalidArgumentException('There must be at least one notifiable resource request provider');
        }
        $this->notifiableResourceRequestProviders = $requestProvidersAsArray;
    }

    /**
     * @inheritDoc
     */
    public function findAllForActivatedNotifications(): \Generator
    {
        $providerSubRequests = $this->getRequestsFromProviders();
        $request = <<<SQL
            {$providerSubRequests}
            ORDER BY `notification_id`, `host_id`, `service_id`;
            SQL;

        $statement = $this->db->prepare($this->translateDbName($request));
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        /**
         * @var iterable<int,array{
         *   notification_id: int,
         *   host_id: int,
         *   host_name: string,
         *   host_alias: string|null,
         *   host_events: int,
         *   service_id: int,
         *   service_name: string,
         *   service_alias: string,
         *   service_events: int,
         *   included_service_events: int
         *  }> $statement
         */
        yield from DbNotifiableResourceFactory::createFromRecords($statement);
    }

    /**
     * @return string
     */
    private function getRequestsFromProviders(): string
    {
        $requests = \array_map(
            fn(NotifiableResourceRequestProviderInterface $provider) => $provider->getNotifiableResourceSubRequest(),
            $this->notifiableResourceRequestProviders
        );

        return \implode(' UNION ', $requests);
    }
}
