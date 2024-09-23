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

namespace Core\Media\Infrastructure\API\Voters;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class MediaVoters extends Voter
{
    public const CREATE_MEDIA = 'create_media';
    public const UPDATE_MEDIA = 'update_media';

    /**
     * {@inheritDoc}
     */
    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::CREATE_MEDIA, self::UPDATE_MEDIA], true);
    }

    /**
     * {@inheritDoc}
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (! $user instanceof ContactInterface) {
            return false;
        }

        return match ($attribute) {
            self::CREATE_MEDIA, self::UPDATE_MEDIA => $user->hasTopologyRole(Contact::ROLE_ADMINISTRATION_PARAMETERS_IMAGES_RW),
            default => throw new \LogicException('Action on media not handled')
        };
    }
}
