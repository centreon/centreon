import { userPermissionsAtom } from '@centreon/ui-context';
import { useAtomValue } from 'jotai';
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
      canViewCheckCommands: userPermissions?.see_check_commands,
      canViewNotificationCommands: userPermissions?.see_notification_commands,
      canViewDiscoveryCommands: userPermissions?.see_discovery_commands,
      canViewMiscellaneousCommands: userPermissions?.see_miscellaneous_commands
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
