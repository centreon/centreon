<?php

namespace Centreon\Infrastructure\Service;

use Centreon\Infrastructure\CentreonLegacyDB\CentreonDBAdapter;
use Psr\Container\ContainerInterface;

/**
 * Compatibility with Doctrine
 */
class CentreonDBManagerService
{
    /** @var string */
    private $defaultManager = 'configuration_db';

    /** @var array<string,mixed> */
    private $manager;

    /**
     * Construct
     *
     * @param ContainerInterface $services
     */
    public function __construct(ContainerInterface $services)
    {
        $this->manager = [
            'configuration_db' => new CentreonDBAdapter($services->get('configuration_db'), $this),
            'realtime_db' => new CentreonDBAdapter($services->get('realtime_db'), $this),
        ];
    }

    public function getAdapter(string $alias): CentreonDBAdapter
    {
        return $this->manager[$alias] ?? null;
    }

    /**
     * Get default adapter with DB connection
     *
     * @return CentreonDBAdapter
     */
    public function getDefaultAdapter(): CentreonDBAdapter
    {
        return $this->manager[$this->defaultManager];
    }

    /**
     * @param mixed $repository
     */
    public function getRepository($repository): mixed
    {
        return $this->manager[$this->defaultManager]
            ->getRepository($repository);
    }
}
