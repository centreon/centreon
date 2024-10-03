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

namespace Centreon\Application\Controller;

use Centreon\Domain\Platform\Interfaces\PlatformServiceInterface;
use Centreon\Domain\Platform\PlatformException;
use Centreon\Domain\PlatformInformation\Exception\PlatformInformationException;
use Centreon\Domain\PlatformInformation\Model\PlatformInformationDtoValidator;
use Centreon\Domain\PlatformInformation\UseCase\V20\UpdatePartiallyPlatformInformation;
use Centreon\Domain\VersionHelper;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * This controller is designed to manage API requests concerning the versions of the different modules, widgets on the
 * Centreon platform.
 */
class PlatformController extends AbstractController
{
    public function __construct(
        private readonly PlatformServiceInterface $informationService,
    ) {
    }

    /**
     * Retrieves the version of modules, widgets, remote pollers from the Centreon Platform.
     *
     * @throws PlatformException
     *
     * @return Response
     */
    public function getVersions(): Response
    {
        $webVersion = $this->informationService->getWebVersion();
        $modulesVersion = $this->informationService->getModulesVersion();
        $widgetsVersion = $this->informationService->getWidgetsVersion($webVersion);

        return new JsonResponse(
            [
                'web' => (object) $this->extractVersion($webVersion),
                'modules' => (object) array_map($this->extractVersion(...), $modulesVersion),
                'widgets' => (object) array_map($this->extractVersion(...), $widgetsVersion),
            ]
        );
    }

    /**
     * Update the platform.
     *
     * @param Request $request
     * @param UpdatePartiallyPlatformInformation $updatePartiallyPlatformInformation
     *
     * @throws \Throwable
     *
     * @return View
     */
    public function updatePlatform(
        Request $request,
        UpdatePartiallyPlatformInformation $updatePartiallyPlatformInformation
    ): View {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $updatePartiallyPlatformInformation->addValidators(
            [
                new PlatformInformationDtoValidator(
                    $this->getParameter('centreon_path')
                    . 'config/json_validator/latest/Centreon/PlatformInformation/Update.json'
                ),
            ]
        );

        $request = json_decode((string) $request->getContent(), true);
        if (! \is_array($request)) {
            throw new BadRequestHttpException(_('Error when decoding sent data'));
        }

        try {
            $updatePartiallyPlatformInformation->execute($request);
        } catch (PlatformInformationException $ex) {
            return match ($ex->getCode()) {
                PlatformInformationException::CODE_FORBIDDEN => $this->view(null, Response::HTTP_FORBIDDEN),
                default => $this->view(null, Response::HTTP_BAD_REQUEST),
            };
        } catch (\Throwable $th) {
           $this->view(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->view(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Extract the major, minor and fix number from the version.
     *
     * @param string $version Version to analyse (ex: 1.2.09)
     *
     * @return array{
     *     version: string,
     *     major: string,
     *     minor: string,
     *     fix: string
     * } (ex: [ 'version' => '1.2.09', 'major' => '1', 'minor' => '2', 'fix' => '09'])
     */
    private function extractVersion(string $version): array
    {
        [$major, $minor, $fix] = explode(
            '.',
            VersionHelper::regularizeDepthVersion($version),
            3
        );

        return compact('version', 'major', 'minor', 'fix');
    }
}
