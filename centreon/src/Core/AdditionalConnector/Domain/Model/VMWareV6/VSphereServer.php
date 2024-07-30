<?php

namespace Core\AdditionalConnector\Domain\Model\VMWareV6;

class VSphereServer
{
    public function __construct(
        private readonly string $name,
        private readonly string $url,
        private readonly string $username,
        private readonly string $password
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
