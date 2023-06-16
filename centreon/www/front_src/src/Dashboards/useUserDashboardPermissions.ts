import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { userAtom } from '@centreon/ui-context';

import { DashboardRole, Dashboard } from './models';

interface useUserDashboardPermissionsState {
  canCreateOrManagerDashboards: boolean;
  hasEditPermission: (dashboard: Dashboard) => boolean;
}

const useUserDashboardPermissions = (): useUserDashboardPermissionsState => {
  const { dashboard: globalPermissions } = useAtomValue(userAtom);

  const canCreateOrManagerDashboards =
    globalPermissions?.createDashboards ||
    globalPermissions?.manageAllDashboards ||
    false;

  const hasEditPermission = (dashboard: Dashboard): boolean => {
    return (
      globalPermissions?.manageAllDashboards ||
      (globalPermissions?.createDashboards &&
        equals(dashboard.ownRole, DashboardRole.editor)) ||
      false
    );
  };

  return {
    canCreateOrManagerDashboards,
    hasEditPermission
  };
};

export default useUserDashboardPermissions;
