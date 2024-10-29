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

namespace Core\Dashboard\Infrastructure\API\PartialUpdateDashboard;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Common\Application\Type\NoValue;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\PartialUpdateDashboard;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\PartialUpdateDashboardRequest;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\PartialUpdateDashboardRequestDto;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PartialUpdateDashboardController extends AbstractController
{
    use LoggerTrait;

    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    /**
     * @param int $dashboardId
     * @param Request $request
     * @param PartialUpdateDashboardRequest $mappedRequest
     * @param PartialUpdateDashboard $useCase
     * @param PartialUpdateDashboardPresenter $presenter
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        int $dashboardId,
        Request $request,
        #[MapRequestPayload] PartialUpdateDashboardRequest $mappedRequest,
        PartialUpdateDashboard $useCase,
        PartialUpdateDashboardPresenter $presenter,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            $partialUpdateDashboardRequest = $mappedRequest->toDto();

            $this->assertThumbnailDataSent($request, $partialUpdateDashboardRequest);

            if (
                $request->files->get('thumbnail_data') !== null
                && ! $partialUpdateDashboardRequest->thumbnail instanceof NoValue
            ) {
                /** @var UploadedFile $thumbnail */
                $thumbnail = $request->files->get('thumbnail_data');
                $partialUpdateDashboardRequest->thumbnail->content = $this->validateAndRetrieveThumbnailContent(
                    $thumbnail,
                );
            }

            $useCase($dashboardId, $partialUpdateDashboardRequest, $presenter);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($exception));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse($exception));
        }

        return $presenter->show();
    }

    /**
     * Assert that if at least one data for thumbnail is sent then both are required
     *
     * @param Request $request
     * @param PartialUpdateDashboardRequestDto $dashboardRequest
     * @throws \InvalidArgumentException
     */
    private function assertThumbnailDataSent(Request $request, PartialUpdateDashboardRequestDto $dashboardRequest): void
    {
        if (
            ($request->files->get('thumbnail_data') && $dashboardRequest->thumbnail instanceof NoValue)
            || (! $request->files->get('thumbnail_data') && ! $dashboardRequest->thumbnail instanceof NoValue)
        ) {
            throw new \InvalidArgumentException('Thumbnail definition and content are both required');
        }
    }

    /**
     * @param UploadedFile $thumbnail
     * @throws HttpException
     * @throws FileException
     * @return string
     */
    private function validateAndRetrieveThumbnailContent(UploadedFile $thumbnail): string
    {
        // Dashboard use case we do only allow png files.
        $errors = $this->validator->validate(
            $thumbnail,
            [
                new Assert\Image([
                    'mimeTypes' => ['image/png'],
                ]),
            ]
        );

        if (count($errors) > 0) {
            throw new HttpException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                implode(
                    "\n",
                    array_map(
                        static fn (ConstraintViolationInterface $exception) => $exception->getMessage(),
                        iterator_to_array($errors),
                    ),
                ),
                new ValidationFailedException($thumbnail, $errors),
            );
        }

        return $thumbnail->getContent();
    }
}
