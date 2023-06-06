import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { userAtom } from '@centreon/ui-context';

import { DashboardRole, Dashboard } from './models';

interface useUserDashboardPermissionsState {
  getHasEditPermission: (dashboard: Dashboard) => boolean;
  isEitherCreatorOrAdministrator: boolean;
}

const useUserDashboardPermissions = (): useUserDashboardPermissionsState => {
  const { dashboard: globalRoles } = useAtomValue(userAtom);

  const isEitherCreatorOrAdministrator =
    globalRoles.createDashboards || globalRoles.manageAllDashboards;

  const getHasEditPermission = (dashboard: Dashboard): boolean => {
    return (
      globalRoles.manageAllDashboards ||
      (isEitherCreatorOrAdministrator &&
        equals(dashboard.ownRole, DashboardRole.editor))
    );
  };

  return {
    getHasEditPermission,
    isEitherCreatorOrAdministrator
  };
};

export default useUserDashboardPermissions;
