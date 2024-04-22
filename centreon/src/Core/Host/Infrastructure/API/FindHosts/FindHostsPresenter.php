<?php

namespace Core\Host\Infrastructure\API\FindHosts;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Host\Application\UseCase\FindHosts\FindHostsPresenterInterface;
use Core\Host\Application\UseCase\FindHosts\FindHostsResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Symfony\Component\Serializer\SerializerInterface;

class FindHostsPresenter extends AbstractPresenter implements FindHostsPresenterInterface
{
    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        private readonly SerializerInterface $serializer,
        PresenterFormatterInterface $presenterFormatter,
        private readonly bool $isCloudPlatform
    )
    {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(FindHostsResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present(
                $this->serializer->normalize(
                    $response,
                    null,
                    [
                        'request_parameters' => $this->requestParameters->toArray(),
                        'groups' => $this->isCloudPlatform ? ['cloud'] : ['on_prem']
                    ]
                )
            );
        }
    }
}