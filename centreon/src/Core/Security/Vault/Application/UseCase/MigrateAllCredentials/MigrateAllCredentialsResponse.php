<?php

namespace Core\Security\Vault\Application\UseCase\MigrateAllCredentials;

class MigrateAllCredentialsResponse
{
    /** @var \Traversable<CredentialRecordedDto|CredentialErrorDto> */
    public \Traversable $results;

    public function __construct()
    {
        $this->results = new \ArrayIterator([]);
    }
}
