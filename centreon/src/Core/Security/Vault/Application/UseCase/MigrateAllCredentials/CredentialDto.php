<?php

namespace Core\Security\Vault\Application\UseCase\MigrateAllCredentials;

class CredentialDto
{
    public int $resourceId;

    public CredentialTypeEnum $type;

    public $name;

    public $value;
}