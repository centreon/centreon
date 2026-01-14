<?php

namespace App\Shared\Infrastructure\Dbal;

use Core\Infrastructure\Common\DatabaseTLSResolver;
use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;

class TLSConnectionFactoryDecorator extends ConnectionFactory
{
    private ConnectionFactory $inner;

    public function __construct(ConnectionFactory $inner)
    {
        $this->inner = $inner;
    }

    public function createConnection(array $params, Configuration|null $config = null, EventManager|null $eventManager = null, array $mappingTypes = [])
    {
        $tlsOptions = DatabaseTLSResolver::getTLSOptions();
        if (!empty($tlsOptions)) {
            $params['options'] = ($params['options'] ?? []) + $tlsOptions;
        }

        return $this->inner->createConnection($params);
    }
}
