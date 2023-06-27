import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { userAtom } from '@centreon/ui-context';

import { Dashboard, DashboardRole } from '../../api/models';

type UseDashboardUserPermissions = {
  canCreateOrManageDashboards: boolean;
  hasEditPermission: (dashboard: Dashboard) => boolean;
};

const useDashboardUserPermissions = (): UseDashboardUserPermissions => {
  const { dashboard: globalPermissions } = useAtomValue(userAtom);

  const canCreateOrManageDashboards =
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
    canCreateOrManageDashboards,
    hasEditPermission
  };
};

export { useDashboardUserPermissions };
