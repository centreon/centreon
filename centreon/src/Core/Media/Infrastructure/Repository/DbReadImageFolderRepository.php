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

namespace Core\Media\Infrastructure\Repository;

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\QueryBuilder\Exception\QueryBuilderException;
use Adaptation\Database\QueryBuilder\QueryBuilderInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Domain\RequestParameters\RequestParameters;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\TransformerException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Domain\TrimmedString;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;
use Core\Common\Infrastructure\RequestParameters\Transformer\SearchRequestParametersTransformer;
use Core\Media\Application\Repository\ReadImageFolderRepositoryInterface;
use Core\Media\Domain\Model\ImageFolder\ImageFolder;
use Core\Media\Domain\Model\ImageFolder\ImageFolderDescription;
use Core\Media\Domain\Model\ImageFolder\ImageFolderId;
use Core\Media\Domain\Model\ImageFolder\ImageFolderName;
use Core\ResourceAccess\Domain\Model\DatasetFilter\ResourceNamesById;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use InvalidArgumentException;

final class DbReadImageFolderRepository extends DatabaseRepository implements ReadImageFolderRepositoryInterface
{
    use SqlMultipleBindTrait;

    public function __construct(
        ConnectionInterface $connection,
        QueryBuilderInterface $queryBuilder,
        private readonly SqlRequestParametersTranslator $sqlRequestTranslator,
    ) {
        parent::__construct($connection, $queryBuilder);

        $this->sqlRequestTranslator
            ->getRequestParameters()
            ->setConcordanceStrictMode(RequestParameters::CONCORDANCE_MODE_STRICT)
            ->setConcordanceErrorMode(RequestParameters::CONCORDANCE_ERRMODE_SILENT);
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameters(RequestParametersInterface $requestParameters): array
    {
        try {
            $this->sqlRequestTranslator->setConcordanceArray(
                [
                    'id' => 'folders.dir_id',
                    'name' => 'folders.dir_name',
                    'alias' => 'folders.dir_alias',
                    'comment' => 'folders.dir_comment',
                ]
            );

            $queryBuilder = $this->connection->createQueryBuilder();

            $queryBuilder
                ->select(
                    <<<'SQL'
                            folders.dir_id AS `id`,
                            folders.dir_name AS `name`,
                            folders.dir_alias AS `alias`,
                            folders.dir_comment AS `comment`
                        SQL
                )
                ->from('`:db`.view_img_dir', 'folders');

            if ($requestParameters->getSearch() !== []) {
                $this->sqlRequestTranslator->appendQueryBuilderWithSearchParameter($queryBuilder);
                $queryBuilder->andWhere("folders.dir_name NOT IN ('centreon-map', 'dashboards', 'ppm')");
            } else {
                $queryBuilder->where("folders.dir_name NOT IN ('centreon-map', 'dashboards', 'ppm')");
            }

            if ($requestParameters->getSort() !== []) {
                $this->sqlRequestTranslator->appendQueryBuilderWithSortParameter($queryBuilder);
            } else {
                $queryBuilder->orderBy('folders.dir_name', 'ASC');
            }

            $this->sqlRequestTranslator->appendQueryBuilderWithPagination($queryBuilder);

            $records = $this->connection->iterateAssociative(
                $this->translateDbName($queryBuilder->getQuery()),
                SearchRequestParametersTransformer::reverseToQueryParameters(
                    $this->sqlRequestTranslator->getSearchValues()
                )
            );

            $folders = [];

            foreach ($records as $record) {
                /** @var array{id:int, name:string, alias:?string, comment:?string} $record */
                $folders[] = $this->createImageFolderFromRecord($record);
            }

            // get total without pagination
            $queryTotal = $queryBuilder
                ->select('COUNT(*)')
                ->resetLimit()
                ->offset(0)
                ->getQuery();

            $total = $this->connection->fetchOne(
                $this->translateDbName($queryTotal),
                SearchRequestParametersTransformer::reverseToQueryParameters(
                    $this->sqlRequestTranslator->getSearchValues()
                )
            );

            $this->sqlRequestTranslator->getRequestParameters()->setTotal($total);

            return $folders;
        } catch (QueryBuilderException|TransformerException|ConnectionException $exception) {
            throw new RepositoryException(
                message: sprintf('An error occurred while retrieving image folders: %s', $exception->getMessage()),
                context: [
                    'request_parameters' => $requestParameters->toArray(),
                ],
                previous: $exception,
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParametersAndAccessGroups(
        RequestParametersInterface $requestParameters,
        array $accessGroups,
    ): array {
        $accessGroupIds = array_map(
            static fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
            $accessGroups
        );

        if ($accessGroupIds === []) {
            return [];
        }

        try {
            $this->sqlRequestTranslator->setConcordanceArray(
                [
                    'id' => 'folders.dir_id',
                    'name' => 'folders.dir_name',
                    'alias' => 'folders.dir_alias',
                    'comment' => 'folders.dir_comment',
                ]
            );

            $queryBuilder = $this->connection->createQueryBuilder();

            [
                'parameters' => $accessGroupQueryParameters,
                'placeholderList' => $accessGroupQueryPlaceHolders,
            ] = $this->createMultipleBindParameters(
                values: $accessGroupIds,
                prefix: 'access_group_id',
                paramType: QueryParameterTypeEnum::INTEGER
            );

            $queryParameters = new QueryParameters($accessGroupQueryParameters);

            $queryBuilder
                ->select(
                    <<<'SQL'
                            folders.dir_id AS `id`,
                            folders.dir_name AS `name`,
                            folders.dir_alias AS `alias`,
                            folders.dir_comment AS `comment`
                        SQL
                )
                ->from(
                    '`:db`.view_img_dir',
                    'folders',
                )
                ->innerJoin(
                    'folders',
                    '`:db`.acl_resources_image_folder_relations',
                    'armdr',
                    'armdr.dir_id = folders.dir_id',
                )
                ->innerJoin(
                    'armdr',
                    '`:db`.acl_res_group_relations',
                    'argr',
                    'argr.acl_res_id = armdr.acl_res_id',
                )
                ->innerJoin(
                    'argr',
                    '`:db`.acl_groups',
                    'ag',
                    "ag.acl_group_id = argr.acl_group_id AND ag.acl_group_id IN ({$accessGroupQueryPlaceHolders})",
                );

            // handle search
            if ($requestParameters->getSearch() !== []) {
                $this->sqlRequestTranslator->appendQueryBuilderWithSearchParameter($queryBuilder);
                $queryBuilder->andWhere("folders.dir_name NOT IN ('centreon-map', 'dashboards', 'ppm')");
            } else {
                $queryBuilder->where("folders.dir_name NOT IN ('centreon-map', 'dashboards', 'ppm')");
            }

            // handle sort
            if ($requestParameters->getSort() !== []) {
                $this->sqlRequestTranslator->appendQueryBuilderWithSortParameter($queryBuilder);
            } else {
                $queryBuilder->orderBy('folders.dir_name', 'ASC');
            }

            // handle pagination
            $this->sqlRequestTranslator->appendQueryBuilderWithPagination($queryBuilder);

            // Handle groupBy
            $queryBuilder->groupBy('folders.dir_id', 'folders.dir_name');

            $queryParametersFromSearch = SearchRequestParametersTransformer::reverseToQueryParameters(
                $this->sqlRequestTranslator->getSearchValues(),
            );

            $queryParameters = $queryParametersFromSearch->mergeWith($queryParameters);

            $records = $this->connection->iterateAssociative(
                $this->translateDbName($queryBuilder->getQuery()),
                $queryParameters,
            );

            $folders = [];

            foreach ($records as $record) {
                /** @var array{id:int, name:string, alias:?string, comment:?string} $record */
                $folders[] = $this->createImageFolderFromRecord($record);
            }

            // get total without pagination
            $queryTotal = $queryBuilder
                ->select('COUNT(*)')
                ->resetLimit()
                ->offset(0)
                ->getQuery();

            $total = $this->connection->fetchOne(
                $this->translateDbName($queryTotal),
                $queryParameters,
            );

            $this->sqlRequestTranslator->getRequestParameters()->setTotal((int) $total);

            return $folders;
        } catch (\Exception $exception) {
            throw new RepositoryException(
                message: sprintf('An error occurred while retrieving image folders: %s', $exception->getMessage()),
                context: [
                    'access_groups' => $accessGroupIds,
                    'request_parameters' => $requestParameters->toArray(),
                ],
                previous: $exception,
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function hasAccessToAllImageFolders(array $accessGroups): bool
    {
        if ($accessGroups === []) {
            return false;
        }

        try {
            $accessGroupIds = array_map(
                static fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
                $accessGroups
            );

            ['parameters' => $queryParameters, 'placeholderList' => $placeHolders]
                = $this->createMultipleBindParameters(
                    values: $accessGroupIds,
                    prefix: 'access_group_id',
                    paramType: QueryParameterTypeEnum::INTEGER
                );

            $request = <<<SQL
                SELECT res.all_image_folders
                FROM `:db`.acl_resources res
                INNER JOIN `:db`.acl_res_group_relations argr
                    ON argr.acl_res_id = res.acl_res_id
                INNER JOIN `:db`.acl_groups ag
                    ON ag.acl_group_id = argr.acl_group_id
                WHERE res.acl_res_activate = '1' AND ag.acl_group_id IN ({$placeHolders})
                SQL;

            $values = $this->connection->fetchFirstColumn(
                $this->translateDbName($request),
                QueryParameters::create($queryParameters),
            );

            return in_array(1, $values, true);
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            throw new RepositoryException(
                message: sprintf('An error occurred while retrieving image folders: %s', $exception->getMessage()),
                context: ['access_groups' => $accessGroups],
                previous: $exception,
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function findExistingFolderIds(array $folderIds): array
    {
        if ($folderIds === []) {
            return [];
        }

        try {
            ['parameters' => $queryParameters, 'placeholderList' => $queryBindString]
                = $this->createMultipleBindParameters(
                    values: $folderIds,
                    prefix: 'directory_id',
                    paramType: QueryParameterTypeEnum::INTEGER,
                );

            $query = <<<SQL
                    SELECT dir_id FROM `:db`.view_img_dir WHERE dir_id IN ({$queryBindString})
                SQL;

            return $this->connection->fetchFirstColumn(
                $this->translateDbName($query),
                QueryParameters::create($queryParameters),
            );
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            throw new RepositoryException(
                message: sprintf(
                    'An error occurred while testing folders accessibility in database: %s',
                    $exception->getMessage()
                ),
                context: ['directory_ids' => $folderIds],
                previous: $exception,
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function findFolderNames(array $folderIds): ResourceNamesById
    {
        $folders = new ResourceNamesById();

        if ($folderIds === []) {
            return $folders;
        }

        try {
            ['parameters' => $queryParameters, 'placeholderList' => $queryBindString]
                = $this->createMultipleBindParameters(
                    values: $folderIds,
                    prefix: 'directory_id',
                    paramType: QueryParameterTypeEnum::INTEGER,
                );

            $query = <<<SQL
                    SELECT dir_id, dir_name FROM `:db`.view_img_dir WHERE dir_id IN ({$queryBindString})
                SQL;

            /** @var string[] $records */
            $records = $this->connection->fetchAllKeyValue(
                $this->translateDbName($query),
                QueryParameters::create($queryParameters),
            );

            foreach ($records as $directoryId => $directoryName) {
                $folders->addName($directoryId, new TrimmedString($directoryName));
            }

            return $folders;
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            throw new RepositoryException(
                message: sprintf('An error occurred while retrieving media folders: %s', $exception->getMessage()),
                context: ['directory_ids' => $folderIds],
                previous: $exception,
            );
        }
    }

    /**
     * @param array{id:int, name:string, alias:?string, comment:?string} $record
     *
     * @throws InvalidArgumentException
     * @return ImageFolder
     */
    private function createImageFolderFromRecord(array $record): ImageFolder
    {
        $folder = new ImageFolder(
            id: new ImageFolderId($record['id']),
            name: new ImageFolderName($record['name']),
        );

        $folder->setAlias(! empty($record['alias']) ? new ImageFolderName($record['alias']) : null);
        $folder->setDescription(! empty($record['comment']) ? new ImageFolderDescription($record['comment']) : null);

        return $folder;
    }
}
