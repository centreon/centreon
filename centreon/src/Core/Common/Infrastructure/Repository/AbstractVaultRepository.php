<?php

declare(strict_types=1);

namespace Core\Common\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractVaultRepository
{
    use LoggerTrait;

    protected ?VaultConfiguration $vaultConfiguration;
    private const DEFAULT_SCHEME = 'https';

    public function __construct(
        protected ReadVaultConfigurationRepositoryInterface $configurationRepository,
        protected HttpClientInterface $httpClient
    ) {
        $this->vaultConfiguration = $configurationRepository->findDefaultVaultConfiguration();
    }

    /**
     * Connect to vault to get an authenticationToken
     *
     * @return string
     * @throws \Exception
     */
    public function getAuthenticationToken(): string
    {
        try {
            $url = $this->vaultConfiguration->getAddress() . ':' .
                $this->vaultConfiguration->getPort() . '/v1/auth/approle/login';
            $url = sprintf("%s://%s", self::DEFAULT_SCHEME, $url);
            $body = [
                "role_id" => $this->vaultConfiguration->getRoleId(),
                "secret_id" => $this->vaultConfiguration->getSecretId(),
            ];
            $this->info('Authenticating to Vault: ' . $url);
            $loginResponse = $this->httpClient->request("POST", $url, ["json" => $body]);
            $content = json_decode($loginResponse->getContent(), true);
        } catch (\Exception $ex) {
            $this->error($url . " did not respond with a 2XX status");
            throw $ex;
        }

        if (! isset($content['auth']['client_token'])) {
            $this->error($url . " Unable to retrieve client token from Vault");
            throw new \Exception('Unable to authenticate to Vault');
        }

        return $content['auth']['client_token'];
    }
}
