<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Centreon\Infrastructure\MonitoringServer\Repository;

use Centreon\Domain\Authentication\Exception\AuthenticationException;
use Centreon\Domain\Common\Assertion\Assertion;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Exception\TimeoutException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\MonitoringServer\Exception\GorgoneDeliveryException;
use Centreon\Domain\MonitoringServer\Interfaces\MonitoringServerConfigurationRepositoryInterface;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Infrastructure\MonitoringServer\Repository\Exception\MonitoringServerConfigurationRepositoryException;
use Core\Common\Infrastructure\Api\InternalApiClient;
use DateTime;
use Security\Domain\Authentication\Interfaces\AuthenticationTokenServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * This class is designed to represent the API repository to manage the generation/move/reload of the monitoring
 * server configuration.
 */
class MonitoringServerConfigurationRepositoryApi implements MonitoringServerConfigurationRepositoryInterface
{
    use LoggerTrait;

    /** @var ContactInterface */
    private $contact;

    /** @var AuthenticationTokenServiceInterface */
    private $authenticationTokenService;

    /** @var HttpClientInterface */
    private $httpClient;

    /** @var string */
    private $serverUri;

    /** @var int */
    private $timeout = 60;

    /** @var int|null timeout of the generation call, the general timeout when not set */
    private ?int $generationTimeout = null;

    /**
     * @param AuthenticationTokenServiceInterface $authenticationTokenService
     * @param ContactInterface $contact
     * @param HttpClientInterface $httpClient
     */
    public function __construct(
        AuthenticationTokenServiceInterface $authenticationTokenService,
        ContactInterface $contact,
        HttpClientInterface $httpClient,
        #[Autowire('%env(CENTREON_INTERNAL_API_BASE_URL)%')]
        private readonly ?string $internalApiBaseUrl = null,
    ) {
        $this->contact = $contact;
        $this->authenticationTokenService = $authenticationTokenService;
        $this->httpClient = $httpClient;
    }

    /**
     * To be used by the dependency injector to increase the timeout limit.
     *
     * @param int $timeout
     *
     * @return MonitoringServerConfigurationRepositoryApi
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * To be used by the dependency injector: timeout of the generation call only, which takes much longer than
     * the other calls on large configurations. Comes from an environment variable: an empty or invalid value
     * keeps the general timeout.
     *
     * @param int|string|null $timeout seconds
     *
     * @return MonitoringServerConfigurationRepositoryApi
     */
    public function setGenerationTimeout(int|string|null $timeout): self
    {
        $timeout = is_string($timeout) ? trim($timeout) : $timeout;
        $this->generationTimeout = (is_int($timeout) || ctype_digit((string) $timeout)) && (int) $timeout > 0
            ? (int) $timeout
            : null;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function generateConfiguration(int $monitoringServerId): void
    {
        $timeout = $this->generationTimeout ?? $this->timeout;
        $this->callHttp(
            'generateFiles.php',
            'generate=true&debug=true&poller=' . $monitoringServerId,
            $timeout,
            sprintf(
                _('The configuration generation did not finish within %d seconds (it may still be running)'),
                $timeout
            ),
        );
    }

    /**
     * @inheritDoc
     */
    public function moveExportFiles(int $monitoringServerId): void
    {
        $this->callHttp('moveFiles.php', 'poller=' . $monitoringServerId);
    }

    /**
     * @inheritDoc
     */
    public function reloadConfiguration(int $monitoringServerId): void
    {
        $this->callHttp('restartPollers.php', 'mode=1&poller=' . $monitoringServerId);
    }

    /**
     * @throws \Assert\AssertionFailedException
     */
    private function initUri(): void
    {
        if ($this->serverUri !== null) {
            return;
        }

        if (empty($this->internalApiBaseUrl)) {
            $serverScheme = $_SERVER['REQUEST_SCHEME'] ?: 'http';
            Assertion::notEmpty($_SERVER['SERVER_NAME']);
            Assertion::notEmpty($_SERVER['REQUEST_URI']);
            $prefixUri = explode('/', $_SERVER['REQUEST_URI'])[1];
            // ex: http://localhost:80/centreon
            $this->serverUri = $serverScheme . '://' . $_SERVER['SERVER_NAME'] . '/' . $prefixUri;

            return;
        }

        // CENTREON_INTERNAL_API_BASE_URL is set when the web server is not reachable
        // through SERVER_NAME from PHP (e.g. httpd and php-fpm in separate containers),
        // as for InternalApiClient. It also allows calls outside of an HTTP request
        // (CLI workers), where $_SERVER has no scheme, host nor URI: the default
        // installation prefix is used then.
        $prefixUri = empty($_SERVER['REQUEST_URI'])
            ? 'centreon'
            : explode('/', $_SERVER['REQUEST_URI'])[1];
        $this->serverUri = InternalApiClient::convertToLocalUrl(
            '/' . $prefixUri,
            $this->internalApiBaseUrl,
            empty($_SERVER['REQUEST_SCHEME']) ? 'http' : $_SERVER['REQUEST_SCHEME'],
            $_SERVER['SERVER_ADDR'] ?? null,
            empty($_SERVER['SERVER_PORT']) ? null : (int) $_SERVER['SERVER_PORT'],
        );
    }

    /**
     * @param string $filePath
     * @param string $payloadBody
     * @param int|null $timeout seconds, the general timeout when null
     * @param string|null $timeoutMessage reason given when the call times out
     *
     * @throws RepositoryException
     * @throws TimeoutException
     * @throws AuthenticationException
     * @throws GorgoneDeliveryException
     */
    private function callHttp(
        string $filePath,
        string $payloadBody,
        ?int $timeout = null,
        ?string $timeoutMessage = null,
    ): void {
        $timeout ??= $this->timeout;
        try {
            $this->initUri();
            $fullUriPath = $this->serverUri . '/include/configuration/configGenerate/xml/' . $filePath;

            $authenticationTokens = $this->authenticationTokenService->findByContact($this->contact);
            if ($authenticationTokens === null) {
                throw AuthenticationException::authenticationTokenNotFound();
            }
            $providerToken = $authenticationTokens->getProviderToken();
            if (
                $providerToken->getExpirationDate() !== null
                && $providerToken->getExpirationDate()->getTimestamp() < (new DateTime())->getTimestamp()
            ) {
                throw AuthenticationException::authenticationTokenExpired();
            }

            $optionPayload = [
                'proxy' => null,
                'no_proxy' => '*',
                'verify_peer' => false,
                'headers' => ['X-AUTH-TOKEN' => $providerToken->getToken()],
                'body' => $payloadBody,
                'timeout' => $timeout,
            ];

            $optionPayload['verify_host'] = $optionPayload['verify_peer'];

            $response = $this->httpClient->request('POST', $fullUriPath, $optionPayload);
            if ($response->getStatusCode() !== 200) {
                throw MonitoringServerConfigurationRepositoryException::apiRequestFailed($response->getStatusCode());
            }

            $xml = $response->getContent();
            if (! empty($xml)) {
                if (($element = simplexml_load_string($xml)) !== false) {
                    if ((string) $element->statuscode !== '0') {
                        $errorCode = (int) $element->errorcode;
                        if (GorgoneDeliveryException::isKnownReason($errorCode)) {
                            throw new GorgoneDeliveryException((string) $element->error, $errorCode);
                        }

                        throw new RepositoryException((string) $element->error);
                    }
                }
            } else {
                throw MonitoringServerConfigurationRepositoryException::responseEmpty();
            }
        } catch (RepositoryException|AuthenticationException|GorgoneDeliveryException $ex) {
            throw $ex;
        } catch (\Assert\AssertionFailedException $ex) {
            throw MonitoringServerConfigurationRepositoryException::errorWhenInitializingApiUri();
        } catch (\Throwable $ex) {
            if ($ex instanceof TransportException && mb_strpos($ex->getMessage(), 'timeout') > 0) {
                throw MonitoringServerConfigurationRepositoryException::timeout(
                    $ex,
                    $timeoutMessage ?? sprintf(_('%s did not answer within %d seconds'), $filePath, $timeout),
                );
            }

            throw MonitoringServerConfigurationRepositoryException::unexpectedError($ex);
        }
    }
}
