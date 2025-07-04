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

namespace Centreon\Infrastructure\Service;

use App\Kernel;
use Centreon\Application\DataRepresenter;
use Centreon\Infrastructure\CentreonLegacyDB\Interfaces\PaginationRepositoryInterface;
use Centreon\ServiceProvider;
use Exception;
use JsonSerializable;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use RuntimeException;

class CentreonPaginationService
{
    public const LIMIT_MAX = 500;

    /** @var CentreonDBManagerService */
    protected $db;

    /** @var \Symfony\Component\Serializer\Serializer */
    protected $serializer;

    /** @var mixed */
    protected $filters;

    /** @var int */
    protected $limit;

    /** @var int */
    protected $offset;

    /** @var string */
    protected $repository;

    /** @var array */
    protected $ordering;

    /** @var array */
    protected $extras;

    /** @var string */
    protected $dataRepresenter;

    /** @var array|null */
    protected $context;

    /** @var \Symfony\Component\DependencyInjection\ContainerInterface */
    private $symfonyContainer;

    /**
     * List of required services
     *
     * @return array
     */
    public static function dependencies(): array
    {
        return [
            ServiceProvider::CENTREON_DB_MANAGER,
            ServiceProvider::SERIALIZER,
        ];
    }

    /**
     * Construct
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->db = $container->get(ServiceProvider::CENTREON_DB_MANAGER);
        $this->serializer = $container->get(ServiceProvider::SERIALIZER);
        $this->symfonyContainer = (Kernel::createForWeb())->getContainer();
    }

    /**
     * Set pagination filters
     *
     * @param mixed $filters
     * @return CentreonPaginationService
     */
    public function setFilters($filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * @return \Symfony\Component\Serializer\Serializer
     */
    public function getSerializer(): \Symfony\Component\Serializer\Serializer
    {
        return $this->serializer;
    }

    /**
     * @return string
     */
    public function getDataRepresenter(): string
    {
        return $this->dataRepresenter;
    }

    /**
     * Set pagination limit
     *
     * @param int $limit
     * @throws RuntimeException
     * @return CentreonPaginationService
     */
    public function setLimit(?int $limit = null): self
    {
        if ($limit !== null && $limit > static::LIMIT_MAX) {
            throw new RuntimeException(
                sprintf(_('Max value of limit has to be %d instead %d'), static::LIMIT_MAX, $limit)
            );
        }
        if ($limit !== null && $limit < 1) {
            throw new RuntimeException(sprintf(_('Minimum value of limit has to be 1 instead %d'), $limit));
        }

        $this->limit = $limit;

        return $this;
    }

    /**
     * Set pagination offset
     *
     * @param int $offset
     * @throws RuntimeException
     * @return CentreonPaginationService
     */
    public function setOffset(?int $offset = null): self
    {
        if ($offset !== null && $offset < 1) {
            throw new RuntimeException(sprintf(_('Minimum value of offset has to be 1 instead %d'), $offset));
        }

        $this->offset = $offset;

        return $this;
    }

    /**
     * Set pagination order
     *
     * @param mixed $field
     * @param mixed $order
     * @throws RuntimeException
     * @return CentreonPaginationService
     */
    public function setOrder($field, $order): self
    {
        $order = (! empty($order) && (strtoupper($order) == 'DESC')) ? $order : 'ASC';

        $this->ordering = ['field' => $field, 'order' => $order];

        return $this;
    }

    /**
     * Set pagination order
     *
     * @param array $extras
     * @throws RuntimeException
     * @return CentreonPaginationService
     */
    public function setExtras($extras): self
    {
        $this->extras = $extras;

        return $this;
    }

    /**
     * Set repository class
     *
     * @param string $repository
     * @throws Exception
     * @return CentreonPaginationService
     */
    public function setRepository(string $repository): self
    {
        $interface = PaginationRepositoryInterface::class;
        $ref = new ReflectionClass($repository);
        $hasInterface = $ref->isSubclassOf($interface);

        if ($hasInterface === false) {
            throw new Exception(sprintf(_('Repository class %s has to implement %s'), $repository, $interface));
        }

        $this->repository = $repository;

        return $this;
    }

    /**
     * Set data representer class
     *
     * @param string $dataRepresenter
     * @throws Exception
     * @return CentreonPaginationService
     */
    public function setDataRepresenter(string $dataRepresenter): self
    {
        $interface = JsonSerializable::class;
        $ref = new ReflectionClass($dataRepresenter);
        $hasInterface = $ref->isSubclassOf($interface);

        if ($hasInterface === false) {
            throw new Exception(
                sprintf(_('Class %s has to implement %s to be DataRepresenter'), $dataRepresenter, $interface)
            );
        }

        $this->dataRepresenter = $dataRepresenter;

        return $this;
    }

    /**
     * Set the Serializer context and if the context is different from null value
     * the list of entities will be normalized
     *
     * @param array $context
     * @return CentreonPaginationService
     */
    public function setContext(?array $context = null): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Get paginated list
     *
     * @return DataRepresenter\Listing
     */
    public function getListing(): DataRepresenter\Listing
    {
        $repository = $this->symfonyContainer->get($this->repository);

        $entities = $repository
            ->getPaginationList($this->filters, $this->limit, $this->offset, $this->ordering, $this->extras);

        $total = $repository->getPaginationListTotal();

        // Serialize list of entities
        if ($this->context !== null) {
            $entities = $this->serializer->normalize($entities, null, $this->context);
        }

        return new DataRepresenter\Listing($entities, $total, $this->offset, $this->limit, $this->dataRepresenter);
    }

    /**
     * Get response data representer with paginated list
     *
     * @return DataRepresenter\Response
     */
    public function getResponse(): DataRepresenter\Response
    {
        return new DataRepresenter\Response($this->getListing());
    }
}
