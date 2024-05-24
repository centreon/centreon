<?php

namespace Core\Security\Vault\Application\UseCase\MigrateAllCredentials;

class CredentialErrorDto
{
    public int $resourceId = 0;

    public CredentialTypeEnum $type = CredentialTypeEnum::TYPE_HOST;

    public string $message = '';

    public string $credentialName = '';
}