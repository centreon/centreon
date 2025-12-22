import { userPermissionsAtom } from '@centreon/ui-context';
import { useAtomValue } from 'jotai';
import { or } from 'ramda';
import { useMemo } from 'react';

interface UseUserPermissions {
  canEditCheckCommands?: boolean;
  canEditNotificationCommands?: boolean;
  canEditDiscoveryCommands?: boolean;
  canEditMiscellaneousCommands?: boolean;
  canViewCheckCommands?: boolean;
  canViewNotificationCommands?: boolean;
  canViewDiscoveryCommands?: boolean;
  canViewMiscellaneousCommands?: boolean;
  canEdit?: boolean;
}

export const useUserPermissions = (): UseUserPermissions => {
  const userPermissions = useAtomValue(userPermissionsAtom);

  const viewerPermissions = useMemo(
    () => ({
      canViewCheckCommands: or(
        userPermissions?.see_check_commands,
        userPermissions?.manage_check_commands
      ),
      canViewNotificationCommands: or(
        userPermissions?.see_notification_commands,
        userPermissions?.manage_notification_commands
      ),
      canViewDiscoveryCommands: or(
        userPermissions?.see_discovery_commands,
        userPermissions?.manage_discovery_commands
      ),
      canViewMiscellaneousCommands: or(
        userPermissions?.see_miscellaneous_commands,
        userPermissions?.manage_miscellaneous_commands
      )
    }),
    []
  );

  const editorPermissions = useMemo(
    () => ({
      canEditCheckCommands: userPermissions?.manage_check_commands,
      canEditNotificationCommands:
        userPermissions?.manage_notification_commands,
      canEditDiscoveryCommands: userPermissions?.manage_discovery_commands,
      canEditMiscellaneousCommands:
        userPermissions?.manage_miscellaneous_commands
    }),
    []
  );

  return {
    ...viewerPermissions,
    ...editorPermissions,
    canEdit: Object.values(editorPermissions).some(Boolean)
  };
};
