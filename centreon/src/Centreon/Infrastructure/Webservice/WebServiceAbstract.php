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

namespace Centreon\Infrastructure\Webservice;

use Centreon\Application\DataRepresenter;
use Centreon\ServiceProvider;
use JsonSerializable;
use Pimple\Container;
use Symfony\Component\Serializer;

/**
 * @OA\Server(
 *      url="{protocol}://{host}/centreon/api",
 *      variables={
 *          "protocol": {"enum": {"http", "https"}, "default": "http"},
 *          "host": {"default": "centreon-dev"}
 *      }
 * )
 */
/**
 * @OA\Info(
 *      title="Centreon Server API",
 *      version="0.1"
 * )
 */
/**
 * @OA\ExternalDocumentation(
 *      url="https://documentation.centreon.com/docs/centreon/en/18.10/api/api_rest/index.html",
 *      description="Official Centreon documentation about REST API"
 * )
 */

/**
 * @OA\Components(
 *      securitySchemes={
 *          "Session": {
 *              "type": "apiKey",
 *              "in": "cookie",
 *              "name": "centreon",
 *              "description": "This type of authorization is mostly used for needs of Centreon Web UI"
 *          },
 *          "AuthToken": {
 *              "type": "apiKey",
 *              "in": "header",
 *              "name": "HTTP_CENTREON_AUTH_TOKEN",
 *              "description": "For external access to the resources that require authorization"
 *          }
 *      }
 * )
 */
abstract class WebServiceAbstract extends \CentreonWebService
{
    /** @var Container */
    protected $di;

    /**
     * Name of the webservice (the value that will be in the object parameter)
     *
     * @return string
     */
    abstract public static function getName(): string;

    /**
     * Getter for DI container
     *
     * @return Container
     */
    public function getDi(): Container
    {
        return $this->di;
    }

    /**
     * Setter for DI container
     *
     * @param Container $di
     */
    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    /**
     * Get URL parameters
     *
     * @return array<mixed>
     */
    public function query(): array
    {
        $request = $_GET;

        return $request;
    }

    /**
     * Get body of request as string
     *
     * @return string
     */
    public function payloadRaw(): string
    {
        $content = file_get_contents('php://input');

        return $content ? (string) $content : '';
    }

    /**
     * Get body of request as decoded JSON
     *
     * @return array
     */
    public function payload(): array
    {
        $request = [];
        $content = $this->payloadRaw();

        if ($content) {
            $request = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $request = [];
            }
        }

        return $request;
    }

    /**
     * Get the Serializer service
     *
     * @return Serializer\Serializer
     */
    public function getSerializer(): Serializer\Serializer
    {
        return $this->di[ServiceProvider::SERIALIZER];
    }

    /**
     * Return success response
     *
     * @param mixed $data
     * @param array $context the context for Serializer
     * @return JsonSerializable
     */
    public function success($data, array $context = []): JsonSerializable
    {
        return new DataRepresenter\Response(
            $this->getSerializer()->normalize($data, null, $context)
        );
    }
}
