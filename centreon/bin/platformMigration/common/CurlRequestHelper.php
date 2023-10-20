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

declare(strict_types=1);

namespace CloudMigration;

/**
 * Provide some methods to faciliate curl requests.
 */
trait CurlRequestHelper {
    private \CentreonRestHttp $curl;

    /**
     * @param string $url
     * @param string $apiToken
     * @param string $method
     * @param null|array<mixed> $body
     *
     * @throws PlatformMigrationException
     *
     * @return array<mixed>
     */
    private function curlCall(
        string $url,
        string $apiToken,
        string $method,
        ?array $body
    ): array {
        $headers = [
            'Content-type: application/json',
            "X-AUTH-TOKEN: {$apiToken}",
        ];
        // TODO should we use certificate ? proxy ?

        try {
            if (! isset($this->curl)) {
                $this->curl = new \CentreonRestHttp();
            }

            return $this->curl->call($url, $method, $body, $headers);
        } catch (\Throwable $ex) {
            throw PlatformMigrationException::requestFailed($ex->getMessage());
        }
    }

    /**
     * In case of paginated endpoint, get all available results.
     *
     * @param string $url
     * @param string $apiToken
     *
     * @throws PlatformMigrationException
     *
     * @return array<string,mixed>
     */
    private function getAllRequest(
        string $url,
        string $apiToken
    ): array {
        /** @var array{result:array<string,mixed>,meta:array{limit:int,total:int}} $response */
        $response = $this->curlCall(
            $url,
            $apiToken,
            'GET',
            null
        );

        if (
            $response !== false
            && $response['meta']['total'] > $response['meta']['limit']
        ) {
            $url .= "?limit={$response['meta']['total']}";

            /** @var array{result:array<string,mixed>,meta:array{limit:int,total:int}} $response */
            $response = $this->curlCall(
                $url,
                $apiToken,
                'GET',
                null
            );
        }

        return $response['result'];
    }
}