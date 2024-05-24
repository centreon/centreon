<?php


namespace Core\Security\Vault\Application\UseCase\MigrateAllCredentials;

class CredentialRecordedDto
{
    public string $uuid = '';

    public int $resourceId = 0;

    public string $vaultPath = '';

    public CredentialTypeEnum $type = CredentialTypeEnum::TYPE_HOST;

    public string $credentialName = '';

}