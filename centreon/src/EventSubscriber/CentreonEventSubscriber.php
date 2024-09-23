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

namespace EventSubscriber;

use \Symfony\Bundle\SecurityBundle\Security;
use Centreon\Application\ApiPlatform;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Entity\EntityCreator;
use Centreon\Domain\Entity\EntityValidator;
use Centreon\Domain\Exception\EntityNotFoundException;
use Centreon\Domain\RequestParameters\{
    Interfaces\RequestParametersInterface, RequestParameters, RequestParametersException
};
use Centreon\Domain\VersionHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\{
    ExceptionEvent, RequestEvent, ResponseEvent
};
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\{
    Exception\AccessDeniedException
};
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * We defined an event subscriber on the kernel event request to create a
 * RequestParameters class according to query parameters and then used in the services
 * or repositories.
 *
 * This class is automatically calls by Symfony through the dependency injector
 * and because it's defined as a service.
 */
class CentreonEventSubscriber implements EventSubscriberInterface
{
    /**
     * If no API header name has been defined in the configuration,
     * this name will be used by default.
     */
    public const DEFAULT_API_HEADER_NAME = 'version';

    /**
     * @param RequestParametersInterface $requestParameters
     * @param Security $security
     * @param ApiPlatform $apiPlatform
     * @param ContactInterface $contact
     * @param LoggerInterface $logger
     * @param string $apiVersionLatest
     * @param string $apiHeaderName
     * @param string $translationPath
     */
    public function __construct(
        readonly private RequestParametersInterface $requestParameters,
        readonly private Security $security,
        readonly private ApiPlatform $apiPlatform,
        readonly private ContactInterface $contact,
        readonly private LoggerInterface $logger,
        readonly private string $apiVersionLatest,
        readonly private string $apiHeaderName,
        readonly private string $translationPath,
    ) {
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return mixed[] The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['initRequestParameters', 9],
                ['defineApiVersionInAttributes', 33],
                ['initUser', 7],
            ],
            KernelEvents::RESPONSE => [
                ['addApiVersion', 10],
            ],
            KernelEvents::EXCEPTION => [
                ['onKernelException', 10],
            ],
        ];
    }

    /**
     * Use to update the api version into all responses.
     *
     * @param ResponseEvent $event
     *
     * @throws InvalidArgumentException
     */
    public function addApiVersion(ResponseEvent $event): void
    {
        $event->getResponse()->headers->add([$this->apiHeaderName => $this->apiVersionLatest]);
    }

    /**
     * Initializes the RequestParameters instance for later use in the service or repositories.
     *
     * @param RequestEvent $request
     *
     * @throws \Exception
     */
    public function initRequestParameters(RequestEvent $request): void
    {
        $query = $request->getRequest()->query->all();

        $limit = filter_var(
            $query[RequestParameters::NAME_FOR_LIMIT] ?? RequestParameters::DEFAULT_LIMIT,
            FILTER_VALIDATE_INT
        );
        if (false === $limit) {
            throw RequestParametersException::integer(RequestParameters::NAME_FOR_LIMIT);
        }
        $this->requestParameters->setLimit($limit);

        $page = filter_var(
            $query[RequestParameters::NAME_FOR_PAGE] ?? RequestParameters::DEFAULT_PAGE,
            FILTER_VALIDATE_INT
        );
        if (false === $page) {
            throw RequestParametersException::integer(RequestParameters::NAME_FOR_PAGE);
        }
        $this->requestParameters->setPage($page);

        if (isset($query[RequestParameters::NAME_FOR_SORT])) {
            $this->requestParameters->setSort($query[RequestParameters::NAME_FOR_SORT]);
        }

        if (isset($query[RequestParameters::NAME_FOR_SEARCH])) {
            $this->requestParameters->setSearch($query[RequestParameters::NAME_FOR_SEARCH]);
        } else {
            // Create search by using parameters in query
            $reservedFields = [
                RequestParameters::NAME_FOR_LIMIT,
                RequestParameters::NAME_FOR_PAGE,
                RequestParameters::NAME_FOR_SEARCH,
                RequestParameters::NAME_FOR_SORT,
                RequestParameters::NAME_FOR_TOTAL,
            ];

            $search = [];
            foreach ($query as $parameterName => $parameterValue) {
                if (
                    in_array($parameterName, $reservedFields, true)
                    || $parameterName !== 'filter'
                    || ! is_array($parameterValue)
                ) {
                    continue;
                }
                foreach ($parameterValue as $subParameterName => $subParameterValues) {
                    if (str_contains($subParameterValues, '|')) {
                        $subParameterValues = explode('|', urldecode($subParameterValues));
                        foreach ($subParameterValues as $value) {
                            $search[RequestParameters::AGGREGATE_OPERATOR_OR][] = [$subParameterName => $value];
                        }
                    } else {
                        $search[RequestParameters::AGGREGATE_OPERATOR_AND][$subParameterName]
                            = urldecode($subParameterValues);
                    }
                }
            }
            if ($json = json_encode($search)) {
                $this->requestParameters->setSearch($json);
            }
        }

        /**
         * Add extra parameters.
         */
        $reservedFields = [
            RequestParameters::NAME_FOR_LIMIT,
            RequestParameters::NAME_FOR_PAGE,
            RequestParameters::NAME_FOR_SEARCH,
            RequestParameters::NAME_FOR_SORT,
            RequestParameters::NAME_FOR_TOTAL,
            'filter',
        ];

        foreach ($request->getRequest()->query->all() as $parameter => $value) {
            if (! in_array($parameter, $reservedFields, true)) {
                $this->requestParameters->addExtraParameter(
                    $parameter,
                    $value
                );
            }
        }
    }

    /**
     * We retrieve the API version from url to put it in the attributes to allow
     * the kernel to use it in routing conditions.
     *
     * @param RequestEvent $event
     *
     * @throws InvalidArgumentException
     */
    public function defineApiVersionInAttributes(RequestEvent $event): void
    {
        $event->getRequest()->attributes->set('version.latest', $this->apiVersionLatest);
        $event->getRequest()->attributes->set('version.is_latest', false);

        $event->getRequest()->attributes->set('version.is_beta', false);
        $event->getRequest()->attributes->set('version.not_beta', true);

        $uri = $event->getRequest()->getRequestUri();
        if (preg_match('/\/api\/([^\/]+)/', $uri, $matches)) {
            $requestApiVersion = $matches[1];
            if ($requestApiVersion[0] === 'v') {
                $requestApiVersion = mb_substr($requestApiVersion, 1);
                $requestApiVersion = VersionHelper::regularizeDepthVersion(
                    $requestApiVersion,
                    1
                );
            }

            if (
                $requestApiVersion === 'latest'
                || VersionHelper::compare($requestApiVersion, $this->apiVersionLatest, VersionHelper::EQUAL)
            ) {
                $event->getRequest()->attributes->set('version.is_latest', true);
                $requestApiVersion = $this->apiVersionLatest;
            }
            if ($requestApiVersion === 'beta') {
                $event->getRequest()->attributes->set('version.is_beta', true);
                $event->getRequest()->attributes->set('version.not_beta', false);
            }

            /**
             * Used for the routing conditions.
             *
             * @todo We need to use an other name because after routing,
             *       its value is overwritten by the value of the 'version' property from uri
             */
            $event->getRequest()->attributes->set('version', $requestApiVersion);

            // Used for controllers
            $event->getRequest()->attributes->set('version_number', $requestApiVersion);
            $this->apiPlatform->setVersion($requestApiVersion);
        }
    }

    /**
     * Used to manage exceptions outside controllers.
     *
     * @param ExceptionEvent $event
     *
     * @throws \InvalidArgumentException
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $flagController = 'Controller';
        $errorIsBeforeController = true;

        // We detect if the exception occurred before the kernel called the controller
        foreach ($event->getThrowable()->getTrace() as $trace) {
            if (
                array_key_exists('class', $trace)
                && mb_strlen($trace['class']) > mb_strlen($flagController)
                && mb_substr($trace['class'], -mb_strlen($flagController)) === $flagController
            ) {
                $errorIsBeforeController = false;
                break;
            }
        }

        /**
         * If Yes and exception code !== 403 (Forbidden access),
         * we create a custom error message.
         * If we don't do that, an HTML error will appear.
         */
        if ($errorIsBeforeController) {
            $message = $event->getThrowable()->getMessage();
            if ($event->getThrowable()->getCode() >= Response::HTTP_INTERNAL_SERVER_ERROR) {
                $errorCode = $event->getThrowable()->getCode();
                $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
            } elseif ($event->getThrowable()->getCode() === Response::HTTP_FORBIDDEN) {
                $errorCode = $event->getThrowable()->getCode();
                $statusCode = Response::HTTP_FORBIDDEN;
            } elseif (
                $event->getThrowable() instanceof NotFoundHttpException
                || $event->getThrowable() instanceof MethodNotAllowedHttpException
            ) {
                $errorCode = Response::HTTP_NOT_FOUND;
                $statusCode = Response::HTTP_NOT_FOUND;
            } elseif ($event->getThrowable()->getPrevious() instanceof ValidationFailedException) {

                $message = '';
                foreach ($event->getThrowable()->getPrevious()->getViolations() as $violation) {
                    $message .= $violation->getPropertyPath() . ': ' . $violation->getMessage() . "\n";
                }
                if ($event->getThrowable() instanceof HttpException) {
                    $errorCode = $event->getThrowable()->getStatusCode();
                    $statusCode = $event->getThrowable()->getStatusCode();
                } else {
                    $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
                    $errorCode = $statusCode;
                }
            } else if ($event->getThrowable() instanceof HttpException) {
                    $errorCode = $event->getThrowable()->getStatusCode();
                    $statusCode = $event->getThrowable()->getStatusCode();
            } else {
                $errorCode = $event->getThrowable()->getCode();
                $statusCode = $event->getThrowable()->getCode()
                    ?: Response::HTTP_INTERNAL_SERVER_ERROR;
            }
            $this->logException($event->getThrowable());
            // Manage exception outside controllers
            $event->setResponse(
                new Response(
                    json_encode([
                        'code' => $errorCode,
                        'message' => $message,
                    ]),
                    (int) $statusCode
                )
            );
        } else {
            $errorCode = $event->getThrowable()->getCode() > 0
                ? $event->getThrowable()->getCode()
                : Response::HTTP_INTERNAL_SERVER_ERROR;
            $httpCode = ($event->getThrowable()->getCode() >= 100 && $event->getThrowable()->getCode() < 600)
                ? (int) $event->getThrowable()->getCode()
                : Response::HTTP_INTERNAL_SERVER_ERROR;

            if ($event->getThrowable() instanceof EntityNotFoundException) {
                $errorMessage = json_encode([
                    'code' => Response::HTTP_NOT_FOUND,
                    'message' => $event->getThrowable()->getMessage(),
                ]);
                $httpCode = Response::HTTP_NOT_FOUND;
            } elseif ($event->getThrowable() instanceof \JMS\Serializer\Exception\ValidationFailedException) {
                $errorMessage = json_encode([
                    'code' => $errorCode,
                    'message' => EntityValidator::formatErrors(
                        $event->getThrowable()->getConstraintViolationList(),
                        true
                    ),
                ]);
            } elseif ($event->getThrowable() instanceof \PDOException) {
                $errorMessage = json_encode([
                    'code' => $errorCode,
                    'message' => 'An error has occurred in a repository',
                ]);
            } elseif ($event->getThrowable() instanceof AccessDeniedException) {
                $errorCode = $event->getThrowable()->getCode();
                $errorMessage = json_encode([
                    'code' => $errorCode,
                    'message' => $event->getThrowable()->getMessage(),
                ]);
            } elseif (get_class($event->getThrowable()) === \Exception::class) {
                $errorMessage = json_encode([
                    'code' => $errorCode,
                    'message' => 'Internal error',
                ]);
            } else {
                $errorMessage = json_encode([
                    'code' => $errorCode,
                    'message' => $event->getThrowable()->getMessage(),
                ]);
            }
            $this->logException($event->getThrowable());
            $event->setResponse(
                new Response($errorMessage, (int) $httpCode)
            );
        }
    }

    /**
     * Set contact if he is logged in.
     */
    public function initUser(): void
    {
        if ($user = $this->security->getUser()) {
            /**
             * @var Contact $user
             */
            EntityCreator::setContact($user);
            /**
             * @var ContactInterface $user
             */
            $this->initLanguage($user);
            $this->initGlobalContact($user);
        }
    }

    /**
     * Used to log the message according to the code and type of exception.
     *
     * @param \Throwable $exception
     */
    private function logException(\Throwable $exception): void
    {
        if (! $exception instanceof HttpExceptionInterface || $exception->getCode() >= 500) {
            $this->logger->critical($exception->getMessage(), ['context' => $exception]);
        } else {
            $this->logger->error($exception->getMessage(), ['context' => $exception]);
        }
    }

    /**
     * Init language to manage translation.
     *
     * @param ContactInterface $user
     */
    private function initLanguage(ContactInterface $user): void
    {
        $locale = $user->getLocale() ?? $this->getBrowserLocale();
        $lang = $locale . '.' . Contact::DEFAULT_CHARSET;

        putenv('LANG=' . $lang);
        setlocale(LC_ALL, $lang);
        bindtextdomain('messages', $this->translationPath);
        bind_textdomain_codeset('messages', Contact::DEFAULT_CHARSET);
        textdomain('messages');
    }

    /**
     * Get browser locale if set in http header.
     *
     * @return string The browser locale
     */
    private function getBrowserLocale(): string
    {
        return isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])
            ? (string) \Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE'])
            : Contact::DEFAULT_LOCALE;
    }

    /**
     * Initialise the contact that will be used as the Service.
     * This will not change the user identified during authentication.
     *
     * @param ContactInterface $user Local contact with information to be used
     */
    private function initGlobalContact(ContactInterface $user): void
    {
        if (! $this->contact instanceof Contact) {
            return;
        }
        $this->contact->setId($user->getId())
            ->setName($user->getName())
            ->setAlias($user->getAlias())
            ->setEmail($user->getEmail())
            ->setTemplateId($user->getTemplateId())
            ->setIsActive($user->isActive())
            ->setAdmin($user->isAdmin())
            ->setTimezone($user->getTimezone())
            ->setLocale($user->getLocale())
            ->setRoles($user->getRoles())
            ->setTopologyRules($user->getTopologyRules())
            ->setAccessToApiRealTime($user->hasAccessToApiRealTime())
            ->setAccessToApiConfiguration($user->hasAccessToApiConfiguration())
            ->setLang($user->getLang())
            ->setAllowedToReachWeb($user->isAllowedToReachWeb())
            ->setToken($user->getToken())
            ->setEncodedPassword($user->getEncodedPassword())
            ->setTimezoneId($user->getTimezoneId())
            ->setDefaultPage($user->getDefaultPage())
            ->setUseDeprecatedPages($user->isUsingDeprecatedPages())
            ->setTheme($user->getTheme() ?? 'light')
            ->setUserInterfaceDensity($user->getUserInterfaceDensity());
    }
}
