<?php

namespace Core\AdditionalConnector\Domain\Model\VMWareV6;

class VMWareConfig
{
    /**
     * @param VSphereServer[] $vSphereServers
     * @param int $port
     */
    public function __construct(private readonly array $vSphereServers, private readonly int $port)
    {
    }

    public function getVSphereServers(): array
    {
        return $this->vSphereServers;
    }

    public function getPort(): int
    {
        return $this->port;
    }
}