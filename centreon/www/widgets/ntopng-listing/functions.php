<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

/**
 * Call the NtopNG probe
 *
 * @param array{login: string, password: string, base_url: string, uri: string} $preferences
 * @return string
 * @throws Exception
 */
function callProbe(array $preferences): string
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $preferences['uri']);
    curl_setopt($curl, CURLOPT_USERPWD, $preferences['login'] . ':' . $preferences['password']);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
    $result = curl_exec($curl);
    curl_close($curl);
    if ($result === false) {
        throw new Exception(sprintf(
            "Can't connect to probe !\n\n(URL API: %s)",
            $preferences['base_url']
        ));
    }
    if ($result === '') {
        throw new Exception(sprintf(
            'No data from the probe ! (Check your credentials)\n\n(URI API : %s)',
            $preferences['uri']
        ));
    }
    return $result;
}

/**
 * Create the link to access the details of the measure
 *
 * @param array{
 *     mode: string,
 *     base_url: string,
 *     interface: int,
 *     sort: string,
 *     top: int,
 *     filter-port: int,
 *     filter-address: string
 * } $preferences
 * @return string
 */
function createLink(array $preferences): string
{
    return match ($preferences['mode']) {
        'top-n-local' => $preferences['base_url']
            . "/lua/rest/v2/get/host/active.lua?ifid="
            . $preferences['interface']
            . "&mode=local&perPage=1000&sortColumn="
            . $preferences['sort']
            . "&limit="
            . $preferences['top'],
        'top-n-remote' => $preferences['base_url']
            . "/lua/rest/v2/get/host/active.lua?ifid="
            . $preferences['interface']
            . "&mode=remote&perPage=1000&sortColumn="
            . $preferences['sort']
            . "&limit="
            . $preferences['top'],
        'top-n-flows', 'top-n-application' => $preferences['base_url']
            . "/lua/rest/v2/get/flow/active.lua?ifid="
            . $preferences['interface']
            . "&mode=remote&perPage=1000&sortColumn="
            . $preferences['sort']
            . "&limit="
            . $preferences['top']
            . (! empty($preferences['filter-address'])
                ? '&host=' . $preferences['filter-address']
                : ''
            )
            . (! empty($preferences['filter-port'])
                ? '&port=' . $preferences['filter-port']
                : ''
            ),
        default => '',
    };
}
