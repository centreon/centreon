import { userPermissionsAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { or } from 'ramda';
import { useMemo } from 'react';

import { CommandType } from './models';

interface UseUserPermissions {
  editorPermissions: Record<CommandType, boolean | undefined>;
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
      canViewDiscoveryCommands: or(
        userPermissions?.see_discovery_commands,
        userPermissions?.manage_discovery_commands
      ),
      canViewMiscellaneousCommands: or(
        userPermissions?.see_miscellaneous_commands,
        userPermissions?.manage_miscellaneous_commands
      ),
      canViewNotificationCommands: or(
        userPermissions?.see_notification_commands,
        userPermissions?.manage_notification_commands
      )
    }),
    []
  );

  const editorPermissions = useMemo(
    () => ({
      [CommandType.Check]: userPermissions?.manage_check_commands,
      [CommandType.Discovery]: userPermissions?.manage_discovery_commands,
      [CommandType.Miscellaneous]:
        userPermissions?.manage_miscellaneous_commands,
      [CommandType.Notification]: userPermissions?.manage_notification_commands
    }),
    []
  );

  return {
    ...viewerPermissions,
    canEdit: Object.values(editorPermissions).some(Boolean),
    editorPermissions
  };
};
