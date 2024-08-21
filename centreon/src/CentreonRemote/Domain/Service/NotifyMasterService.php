<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace CentreonRemote\Domain\Service;

use Centreon\Domain\Repository\InformationsRepository;
use Centreon\Infrastructure\Service\CentreonDBManagerService;
use Curl\Curl;

class NotifyMasterService
{
    /**
     * fail constants.
     */
    public const CANT_RESOLVE_HOST = 'Could not resolve the host';
    public const CANT_CONNECT = 'Could not connect';
    public const TIMEOUT = 'Timeout';
    public const UNKNOWN_ERROR = 'Unknown Error';
    public const NO_APP_KEY = 'No Application Key found';

    /**
     * statuses.
     */
    public const SUCCESS = 'success';
    public const FAIL = 'fail';

    /** @var Curl */
    private $curl;

    /**
     * NotifyMasterService constructor.
     *
     * @param CentreonDBManagerService $dbManager
     */
    public function __construct(private CentreonDBManagerService $dbManager)
    {
    }

    /**
     * @param Curl $curl
     */
    public function setCurl(Curl $curl): void
    {
        $this->curl = $curl;
    }

    /**
     * @return Curl
     */
    public function getCurl(): Curl
    {
        return $this->curl;
    }

    /**
     * Ping the master IP requesting to be slave for it.
     *
     * @param string $ip The IP address of the master
     * @param bool $noCheckCertificate To do not check SLL CA on master
     * @param bool $noProxy
     * @param (string|null|false)[] $data The information for the master how to contact the remote
     *
     * @throws \ErrorException
     *
     * @return string[]
     */
    public function pingMaster($ip, $data, $noCheckCertificate = false, $noProxy = false)
    {

        $url = "{$ip}/centreon/api/external.php?object=centreon_remote_server&action=addToWaitList";
        $repository = $this->dbManager->getRepository(InformationsRepository::class);
        $version = $repository->getOneByKey('version');

        try {
            $curlData = [
                'version' => $version->getValue(),
                'http_method' => $data['remoteHttpMethod'] ?? 'http',
                'http_port' => $data['remoteHttpPort'] ?? '',
                'no_check_certificate' => $data['remoteNoCheckCertificate'] ?? 0,
            ];

            if ($noCheckCertificate) {
                $this->getCurl()->setOpt(CURLOPT_SSL_VERIFYPEER, false);
            }
            if ($noProxy) {
                $this->getCurl()->setOpt(CURLOPT_PROXY, false);
            }

            $this->getCurl()->post($url, $curlData);

            if ($this->getCurl()->error) {
                $details = match ($this->getCurl()->error_code) {
                    6 => self::CANT_RESOLVE_HOST,
                    7 => self::CANT_CONNECT,
                    28 => self::TIMEOUT,
                    default => self::UNKNOWN_ERROR,
                };

                return [
                    'status' => 'fail',
                    'details' => $details,
                ];
            }
        } catch (\ErrorException $e) {
            return [
                'status' => self::FAIL,
                'details' => self::UNKNOWN_ERROR,
            ];
        }

        return ['status' => self::SUCCESS];
    }
}
