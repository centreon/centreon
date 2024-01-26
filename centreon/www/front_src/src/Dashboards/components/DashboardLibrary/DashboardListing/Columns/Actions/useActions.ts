import { useState } from 'react';

import { isNil } from 'ramda';

import { useDashboardConfig } from '../../../DashboardConfig/useDashboardConfig';
import { useDashboardDelete } from '../../../../../hooks/useDashboardDelete';
import { useDashboardAccessRights } from '../../../DashboardAccessRights/useDashboardAccessRights';

interface UseActionsState {
  closeMoreActions: () => void;
  deleteDashboard: () => void;
  editAccessRights: () => void;
  editDashboard: () => void;
  isNestedRow: boolean;
  moreActionsOpen: HTMLElement | null;
  openMoreActions: (event) => void;
}

const useActions = (row): UseActionsState => {
  const [moreActionsOpen, setMoreActionsOpen] = useState(null);

  const { editDashboard } = useDashboardConfig();
  const deleteDashboard = useDashboardDelete();
  const { editAccessRights } = useDashboardAccessRights();

  const openMoreActions = (event): void => setMoreActionsOpen(event.target);
  const closeMoreActions = (): void => setMoreActionsOpen(null);

  const isNestedRow = !isNil(row?.role);

  return {
    closeMoreActions,
    deleteDashboard: deleteDashboard(row),
    editAccessRights: editAccessRights(row),
    editDashboard: editDashboard(row),
    isNestedRow,
    moreActionsOpen,
    openMoreActions
  };
};

export default useActions;
