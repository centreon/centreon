<?php

namespace Core\Security\Vault\Infrastructure\API\FindVaultConfiguration;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Security\Vault\Application\UseCase\FindVaultConfiguration\FindVaultConfigurationPresenterInterface;
use Core\Security\Vault\Application\UseCase\FindVaultConfiguration\FindVaultConfigurationResponse;

final class FindVaultConfigurationPresenter extends AbstractPresenter implements FindVaultConfigurationPresenterInterface
{
    public function presentResponse(FindVaultConfigurationResponse|ResponseStatusInterface $data): void
    {
        if ($data instanceof ResponseStatusInterface) {
            $this->setResponseStatus($data);
        } else {
            $this->present([
                'address' => $data->address,
                'port' => $data->port,
                'root_path' => $data->rootPath,
            ]);
        }
    }
}