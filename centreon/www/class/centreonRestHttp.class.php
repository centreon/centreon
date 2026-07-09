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

require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonLog.class.php';

/**
 * Class
 *
 * @class CentreonRestHttp
 * @description Utils class for call HTTP JSON REST
 */
class CentreonRestHttp
{
    /** @var string The content type : default application/json */
    private $contentType = 'application/json';

    /** @var string|null using a proxy */
    private $proxy = null;

    /** @var string proxy authentication information */
    private $proxyAuthentication = null;

    /** @var Psr\Log\LoggerInterface|null logger for call errors (caller-supplied or built from a log file name) */
    private $logger = null;

    /**
     * CentreonRestHttp constructor
     *
     * @param string $contentType The content type
     * @param string|Psr\Log\LoggerInterface|null $logFile a dedicated log file name (e.g.
     *                                                     'license-manager.log') or a ready logger
     *
     * @throws PDOException
     */
    public function __construct($contentType = 'application/json', $logFile = null)
    {
        $this->getProxy();
        $this->contentType = $contentType;
        if ($logFile instanceof Psr\Log\LoggerInterface) {
            $this->logger = $logFile;
        } elseif (is_string($logFile) && $logFile !== '') {
            // A historical caller passes a dedicated file name (e.g. 'license-manager.log').
            // Route it to a dedicated module channel on the unified Monolog pipeline so the
            // historical file name is preserved while records get masking and formatting.
            // A malformed name must never break the HTTP client: degrade to a no-op logger.
            try {
                $this->logger = Adaptation\Log\Logger::create(
                    Adaptation\Log\Channel\ModuleLogChannel::fromLogFileName($logFile)
                );
            } catch (Adaptation\Log\Exception\LoggerException) {
                // Strip control chars so a rejected name cannot forge lines in error_log.
                $safeLogFile = (string) preg_replace('/[[:cntrl:]]/', '?', $logFile);
                error_log(sprintf('CentreonRestHttp: invalid log file "%s"', $safeLogFile));
                $this->logger = new Psr\Log\NullLogger();
            }
        }
    }

    /**
     * Call the http rest endpoint
     *
     * @param string $url The endpoint url
     * @param string $method The HTTP method
     * @param array|null $data The data to send on the request
     * @param array $headers The extra headers without Content-Type
     * @param bool $throwContent
     * @param bool $noCheckCertificate To disable CURLOPT_SSL_VERIFYPEER
     * @param bool $noProxy To disable CURLOPT_PROXY
     *
     * @throws RestBadRequestException
     * @throws RestConflictException
     * @throws RestForbiddenException
     * @throws RestInternalServerErrorException
     * @throws RestMethodNotAllowedException
     * @throws RestNotFoundException
     * @throws RestUnauthorizedException
     * @return array The result content
     */
    public function call($url, $method = 'GET', $data = null, $headers = [], $throwContent = false, $noCheckCertificate = false, $noProxy = false)
    {
        // Add content type to headers
        $headers[] = 'Content-type: ' . $this->contentType;
        $headers[] = 'Connection: close';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($noCheckCertificate) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        if (! $noProxy && ! is_null($this->proxy)) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
            if (! is_null($this->proxyAuthentication)) {
                curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxyAuthentication);
            }
        }

        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                break;
            case 'GET':
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                break;
            default:
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                break;
        }

        if (! is_null($data)) {
            if (isset($this->contentType) && $this->contentType == 'application/json') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (! $http_code) {
            $http_code = 404;
        }

        curl_close($ch);

        $decodedContent = '';
        if ($result) {
            $decodedContent = json_decode($result, true);

            // if it is not possible to parse json, then result is probably a string
            if (! is_array($decodedContent) && is_string($result)) {
                $decodedContent = [
                    'message' => $result,
                ];
            }
        }

        // Manage HTTP status code
        $exceptionClass = null;
        $logMessage = 'Unknown HTTP error';
        switch ($http_code) {
            case 200:
            case 201:
            case 204:
                break;
            case 400:
                $exceptionClass = 'RestBadRequestException';
                break;
            case 401:
                $exceptionClass = 'RestUnauthorizedException';
                break;
            case 403:
                $exceptionClass = 'RestForbiddenException';
                break;
            case 404:
                $exceptionClass = 'RestNotFoundException';
                $logMessage = 'Page not found';
                break;
            case 405:
                $exceptionClass = 'RestMethodNotAllowedException';
                break;
            case 409:
                $exceptionClass = 'RestConflictException';
                break;
            case 500:
            default:
                $exceptionClass = 'RestInternalServerErrorException';
                break;
        }

        if (! is_null($exceptionClass)) {
            if ($throwContent && is_array($decodedContent)) {
                $message = json_encode($decodedContent);
            } elseif (isset($decodedContent['message'])) {
                $message = $decodedContent['message'];
            } else {
                $message = $logMessage;
            }
            $this->insertLog($message, $url, $exceptionClass);

            throw new $exceptionClass($message);
        }

        // Return the content
        return $decodedContent;
    }

    /**
     * @param string $url
     * @param string|int $port
     *
     * @return void
     */
    public function setProxy($url, $port): void
    {
        if (isset($url) && ! empty($url)) {
            $this->proxy = $url;
            if ($port) {
                $this->proxy .= ':' . $port;
            }
        }
    }

    /**
     * @param $output
     * @param $url
     * @param $type
     *
     * @return void
     */
    private function insertLog($output, $url, $type = 'RestInternalServerErrorException'): void
    {
        if (is_null($this->logger)) {
            return;
        }

        // url and response go in the context so the platform sanitizer can redact
        // query-string secrets and cap their length; the message stays secret-free.
        // Logging must never replace the REST exception being reported, so a write
        // failure (or a throwing injected logger) is contained here.
        try {
            $this->logger->error(
                sprintf('[%s] REST call failed', $type),
                ['url' => $url, 'response' => $output]
            );
        } catch (Throwable $e) {
            // Strip control chars so a throwing logger's message cannot forge lines in error_log.
            $safeMessage = (string) preg_replace('/[[:cntrl:]]/', '?', $e->getMessage());
            error_log(sprintf('CentreonRestHttp: failed to write REST error log: %s', $safeMessage));
        }
    }

    /**
     * @throws PDOException
     * @return void
     */
    private function getProxy(): void
    {
        $db = new CentreonDB();
        $query = 'SELECT `key`, `value` '
            . 'FROM `options` '
            . 'WHERE `key` IN ( '
            . '"proxy_url", "proxy_port", "proxy_user", "proxy_password" '
            . ') ';
        $res = $db->query($query);
        while ($row = $res->fetchRow()) {
            $dataProxy[$row['key']] = $row['value'];
        }

        if (isset($dataProxy['proxy_url']) && ! empty($dataProxy['proxy_url'])) {
            $this->proxy = $dataProxy['proxy_url'];
            if ($dataProxy['proxy_port']) {
                $this->proxy .= ':' . $dataProxy['proxy_port'];
            }

            // Proxy basic authentication
            if (isset($dataProxy['proxy_user']) && ! empty($dataProxy['proxy_user'])
                && isset($dataProxy['proxy_password']) && ! empty($dataProxy['proxy_password'])) {
                $this->proxyAuthentication = $dataProxy['proxy_user'] . ':' . $dataProxy['proxy_password'];
            }
        }
    }
}
