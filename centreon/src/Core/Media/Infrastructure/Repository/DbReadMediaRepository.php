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

namespace Core\Media\Infrastructure\Repository;

use Assert\AssertionFailedException;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\RequestParameters\SqlRequestParametersTranslator;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Media\Domain\Model\Media;
use Psr\Log\LoggerInterface;

/**
 * @phpstan-type _Media array{
 *     img_id: int,
 *     img_path: string,
 *     dir_name: string,
 *     img_comment: string,
 * }
 */
class DbReadMediaRepository extends AbstractRepositoryRDB implements ReadMediaRepositoryInterface
{
    private const MAX_ITEMS_BY_REQUEST = 100;

    public function __construct(DatabaseConnection $db, readonly private LoggerInterface $logger)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function existsByPath(string $path): bool
    {
        $pathInfo = pathInfo($path);
        if ($path === '' || $pathInfo['filename'] === '' || empty($pathInfo['dirname'])) {
            return false;
        }

        $statement = $this->db->prepare(
            $this->translateDbName(<<<'SQL'
                SELECT 1
                FROM `:db`.`view_img` img
                INNER JOIN `:db`.`view_img_dir_relation` rel
                    ON rel.img_img_id = img.img_id
                INNER JOIN `:db`.`view_img_dir` dir
                    ON dir.dir_id = rel.dir_dir_parent_id
                WHERE img_path = :media_name
                    AND dir.dir_name = :media_path
                SQL
            )
        );
        $statement->bindValue(':media_name', $pathInfo['basename']);
        $statement->bindValue(':media_path', $pathInfo['dirname']);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * To avoid loading all database elements at once, this iterator allows you to retrieve them in blocks of
     *  MAX_ITEMS_BY_REQUEST elements.
     *
     * {@inheritDoc}
     */
    public function findAll(): \Iterator&\Countable
    {
        return new class ($this->db, self::MAX_ITEMS_BY_REQUEST, $this->createMedia(...), $this->logger)
            extends AbstractRepositoryRDB
            implements \Iterator, \Countable
        {
            /** @var callable */
            protected $createMedia;

            private int $position = 0;

            private int $requestIndex = 0;

            private int $totalItems = 0;

            private \PDOStatement|false $statement = false;

            /**
             * @var array{
             *    img_id: int,
             *    img_path: string,
             *    img_comment: string,
             *    dir_name: string
             *  }|false
             */
            private array|false $currentItem;

            public function __construct(
                protected DatabaseConnection $db,
                readonly private int $maxItemByRequest,
                callable $createMedia,
                readonly private LoggerInterface $logger
            ) {
                $this->createMedia = $createMedia;
            }

            public function current(): Media
            {
                return ($this->createMedia)($this->currentItem);
            }

            public function next(): void
            {
                $this->position++;
                if (! $this->statement || ($nextItem = $this->statement->fetch()) === false) {
                    if ($this->valid()) {
                        // There are still some items to be retrieved from the database
                        $this->requestIndex += $this->maxItemByRequest;
                        $this->loadDatabase();
                    }
                } else {
                    /**
                     * @var array{
                     *     img_id: int,
                     *     img_path: string,
                     *     dir_name: string,
                     *     img_comment: string,
                     * } $nextItem
                     */
                    $this->currentItem = $nextItem;
                }
            }

            public function key(): int
            {
                return $this->position;
            }

            public function valid(): bool
            {
                return $this->position < $this->totalItems;
            }

            public function rewind(): void
            {
                $this->position = 0;
                $this->requestIndex = 0;
                $this->totalItems = 0;
                $this->loadDatabase();
            }

            public function count(): int
            {
                if (! $this->statement) {
                    $request = <<<'SQL'
                        SELECT COUNT(*)
                        FROM `:db`.`view_img` img
                        INNER JOIN `:db`.`view_img_dir_relation` rel
                            ON rel.img_img_id = img.img_id
                        INNER JOIN `:db`.`view_img_dir` dir
                            ON dir.dir_id = rel.dir_dir_parent_id
                        SQL;

                    $this->statement = $this->db->query($this->translateDbName($request))
                        ?: throw new \Exception('Impossible to retrieve a PDO Statement');
                    $this->totalItems = (int) $this->statement->fetchColumn();
                }

                return $this->totalItems;
            }

            private function loadDatabase(): void
            {
                $this->logger->debug(
                    sprintf(
                        'Loading media from %d/%d',
                        $this->requestIndex,
                        $this->maxItemByRequest
                    )
                );
                $request = <<<'SQL'
                    SELECT SQL_CALC_FOUND_ROWS
                        `img`.img_id,
                        `img`.img_path,
                        `img`.img_comment,
                        `dir`.dir_name
                    FROM `:db`.`view_img` img
                    INNER JOIN `:db`.`view_img_dir_relation` rel
                        ON rel.img_img_id = img.img_id
                    INNER JOIN `:db`.`view_img_dir` dir
                        ON dir.dir_id = rel.dir_dir_parent_id
                    ORDER BY img_id
                    LIMIT :from, :max_item_by_request
                    SQL;

                $this->statement = $this->db->prepare($this->translateDbName($request));
                $this->statement->bindValue(':from', $this->requestIndex, \PDO::PARAM_INT);
                $this->statement->bindValue(':max_item_by_request', $this->maxItemByRequest, \PDO::PARAM_INT);
                $this->statement->setFetchMode(\PDO::FETCH_ASSOC);
                $this->statement->execute();

                $result = $this->db->query('SELECT FOUND_ROWS()');

                if ($result !== false && ($total = $result->fetchColumn()) !== false) {
                    $this->totalItems = (int) $total;
                }
                /**
                 * @var array{
                 *     img_id: int,
                 *     img_path: string,
                 *     dir_name: string,
                 *     img_comment: string,
                 * } $result|false
                 */
                $result = $this->statement->fetch();
                $this->currentItem = $result;
            }
        };
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameters(RequestParametersInterface $requestParameters): \Traversable
    {
        $sqlTranslator = new SqlRequestParametersTranslator($requestParameters);
        $sqlTranslator->setConcordanceArray([
            'id' => 'img_id',
            'filename' => 'img_path',
            'directory' => 'dir_name',
        ]);
        $request = <<<'SQL'
            SELECT SQL_CALC_FOUND_ROWS
                `img`.img_id,
                `img`.img_path,
                `img`.img_comment,
                `dir`.dir_name
            FROM `:db`.`view_img` img
            INNER JOIN `:db`.`view_img_dir_relation` rel
                ON rel.img_img_id = img.img_id
            INNER JOIN `:db`.`view_img_dir` dir
                ON dir.dir_id = rel.dir_dir_parent_id
            SQL;

        $searchRequest = $sqlTranslator->translateSearchParameterToSql();
        if ($searchRequest !== null) {
            $request .= $searchRequest;
        }

        // Handle sort
        $sortRequest = $sqlTranslator->translateSortParameterToSql();
        $request .= $sortRequest !== null ? $sortRequest : ' ORDER BY img_id';
        $request .= $sqlTranslator->translatePaginationToSql();
        $statement = $this->db->prepare($this->translateDbName($request));
        foreach ($sqlTranslator->getSearchValues() as $key => $data) {
            /** @var int $type */
            $type = key($data);
            $value = $data[$type];
            $statement->bindValue($key, $value, $type);
        }
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();
        $result = $this->db->query('SELECT FOUND_ROWS()');

        if ($result !== false && ($total = $result->fetchColumn()) !== false) {
            $sqlTranslator->getRequestParameters()->setTotal((int) $total);
        }

        return new class($statement, $this->createMedia(...)) implements \IteratorAggregate {
            public function __construct(
                readonly private \PDOStatement $statement,
                readonly private \Closure $factory,
            ) {
            }

            public function getIterator(): \Traversable
            {
                foreach ($this->statement as $result) {
                    yield ($this->factory)($result);
                }
            }
        };
    }

    /**
     * @param array<string, int|string> $data
     *
     * @throws AssertionFailedException
     *
     * @return Media
     */
    private function createMedia(array $data): Media
    {
        return new Media(
            (int) $data['img_id'],
            (string) $data['img_path'],
            (string) $data['dir_name'],
            (string) $data['img_comment'],
            null
        );
    }
}
