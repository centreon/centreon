<?php

declare(strict_types=1);

namespace App\Monitoring\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Monitoring\Application\Notification\CreateNotificationCommand;
use App\Monitoring\Domain\Aggregate\Notification\Notification;
use App\Monitoring\Domain\Aggregate\Notification\NotificationName;
use App\Monitoring\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Monitoring\Domain\Exception\TimePeriodNotFoundException;
use App\Monitoring\Infrastructure\ApiPlatform\Resource\Notification\CreateNotificationDto;
use App\Monitoring\Infrastructure\ApiPlatform\Resource\Notification\NotificationResource;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<CreateNotificationDto, NotificationResource>
 */
final readonly class CreateNotificationProcessor implements ProcessorInterface {

    public function __construct(
        private CommandBus $commandBus,
        #[Autowire(service: NotificationResourceTransformer::class)]
        private TransformerInterface $transformer,
        private Security $security,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);

        $command = new CreateNotificationCommand(
            name: new NotificationName($data->name),
            isActivated: $data->isActivated,
            timePeriodId: new TimePeriodId($data->timeperiodId),
            creatorId: $credentialUser->credential->userId->value,
        );

        try {
            $model = $this->commandBus->execute($command);
        } catch (TimePeriodNotFoundException $e) {
            throw new ValidationException('Time period does not exist');
        }
        Assert::isInstanceOf($model, Notification::class);
        return $this->transformer->transform($model);
    }
}
