import { userPermissionsAtom } from '@centreon/ui-context';
import { useAtomValue } from 'jotai';

export const useCanManageCommand = () => {
  const userPermissions = useAtomValue(userPermissionsAtom);

  const {
    manage_check_commands,
    manage_notification_commands,
    manage_discovery_commands,
    manage_miscellaneous_commands
  } = userPermissions;

  const canEditCheckCommands = manage_check_commands;
  const canEditNotificationCommands = manage_notification_commands;
  const canEditDiscoveryCommands = manage_discovery_commands;
  const canEditMiscellaneousCommands = manage_miscellaneous_commands;

  const canEdit =
    canEditCheckCommands ||
    canEditNotificationCommands ||
    canEditDiscoveryCommands ||
    canEditMiscellaneousCommands;

  return {
    canEdit,
    canEditCheckCommands,
    canEditNotificationCommands,
    canEditDiscoveryCommands,
    canEditMiscellaneousCommands
  };
};
