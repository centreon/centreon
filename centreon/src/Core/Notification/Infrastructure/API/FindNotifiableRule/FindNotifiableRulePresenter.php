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

namespace Core\Notification\Infrastructure\API\FindNotifiableRule;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Notification\Application\UseCase\FindNotifiableRule\FindNotifiableRulePresenterInterface;
use Core\Notification\Application\UseCase\FindNotifiableRule\FindNotifiableRuleResponse;
use Core\Notification\Application\UseCase\FindNotifiableRule\Response\ContactDto;

final class FindNotifiableRulePresenter extends AbstractPresenter implements FindNotifiableRulePresenterInterface
{
    public function presentResponse(FindNotifiableRuleResponse|ResponseStatusInterface $data): void
    {
        if ($data instanceof FindNotifiableRuleResponse) {
            $this->present([
                'notification_id' => $data->notificationId,
                'channels' => [
                    'email' => null === $data->channels->email ? null : [
                        'subject' => $data->channels->email->subject,
                        'formatted_message' => $data->channels->email->formattedMessage,
                        'contacts' => array_map(
                            static fn(ContactDto $contact) => [
                                'email_address' => $contact->emailAddress,
                                'full_name' => $contact->fullName,
                            ],
                            $data->channels->email->contacts
                        ),
                    ],
                    'slack' => null === $data->channels->slack ? null : [
                        'slack_channel' => $data->channels->slack->slackChannel,
                        'message' => $data->channels->slack->message,
                    ],
                    'sms' => null === $data->channels->sms ? null : [
                        'phone_number' => $data->channels->sms->phoneNumber,
                        'message' => $data->channels->sms->message,
                    ],
                ],
            ]);
        } else {
            $this->setResponseStatus($data);
        }
    }
}
