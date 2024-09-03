<?php

namespace Core\Host\Infrastructure\API\Voters;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class HostVoters extends Voter
{
    const WRITE_HOST = 'write_host';
    const READ_HOST = 'read_host';
    const ALLOWED_ATTRIBUTES = [self::WRITE_HOST, self::READ_HOST];

    protected function supports($attribute, $subject): bool
    {
        if (!in_array($attribute, self::ALLOWED_ATTRIBUTES)) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof ContactInterface) {
            return false;
        }

        return match($attribute) {
            self::WRITE_HOST => $this->canWrite($user),
            self::READ_HOST => $this->canRead($user),
            default => throw new \LogicException('This code should not be reached!')
        };
    }

    private function canWrite(ContactInterface $user): bool
    {
        return $user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_WRITE);
    }

    private function canRead(ContactInterface $user): bool
    {
        return $user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_READ)
            || $user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_WRITE);
    }


}