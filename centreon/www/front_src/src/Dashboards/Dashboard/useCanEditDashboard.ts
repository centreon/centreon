import { useMemo } from 'react';

import { useAtomValue } from 'jotai';

import { useDashboardUserPermissions } from '../components/DashboardUserPermissions/useDashboardUserPermissions';

import useDashboardDetails, { routerParams } from './useDashboardDetails';
import { isEditingAtom } from './atoms';

export const useCanEditProperties = (): {
  canEdit?: boolean;
  canEditField?: boolean;
} => {
  const { dashboardId } = routerParams.useParams();
  const { dashboard } = useDashboardDetails({
    dashboardId: dashboardId as string
  });

  const isEditing = useAtomValue(isEditingAtom);

  const { hasEditPermission } = useDashboardUserPermissions();

  const canEdit = useMemo(
    () => dashboard && hasEditPermission(dashboard),
    [dashboard]
  );

  return {
    canEdit,
    canEditField: canEdit && isEditing
  };
};
