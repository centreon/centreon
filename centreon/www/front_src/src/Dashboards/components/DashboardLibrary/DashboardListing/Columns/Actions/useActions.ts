import { useState } from 'react';

import { isNil } from 'ramda';
import { useSetAtom } from 'jotai';

import { useDashboardConfig } from '../../../DashboardConfig/useDashboardConfig';
import { useDashboardDelete } from '../../../../../hooks/useDashboardDelete';
import { unformatDashboard } from '../../utils';
import { askBeforeRevokeAtom } from '../../atom';
import { isSharesOpenAtom } from '../../../../../atoms';

interface UseActionsState {
  closeMoreActions: () => void;
  deleteDashboard: () => void;
  editAccessRights: () => void;
  editDashboard: () => void;
  isNestedRow: boolean;
  moreActionsOpen: HTMLElement | null;
  openAskBeforeRevoke: () => void;
  openMoreActions: (event) => void;
}

const useActions = (row): UseActionsState => {
  const [moreActionsOpen, setMoreActionsOpen] = useState(null);
  const setAskBeforeRevoke = useSetAtom(askBeforeRevokeAtom);

  const { editDashboard } = useDashboardConfig();
  const deleteDashboard = useDashboardDelete();
  const setIsSharesOpen = useSetAtom(isSharesOpenAtom);

  const openMoreActions = (event): void => setMoreActionsOpen(event.target);
  const closeMoreActions = (): void => setMoreActionsOpen(null);
  const openShares = (dashboard) => () => setIsSharesOpen(dashboard);

  const isNestedRow = !isNil(row?.role);

  const unformattedDashboard = isNestedRow ? row : unformatDashboard(row);

  const openAskBeforeRevoke = (): void => {
    setAskBeforeRevoke({
      dashboardId: row.dashboardId,
      user: {
        id: row.id,
        name: row.name,
        type: row.type
      }
    });
  };

  return {
    closeMoreActions,
    deleteDashboard: deleteDashboard(unformattedDashboard),
    editAccessRights: openShares(unformattedDashboard),
    editDashboard: editDashboard(unformattedDashboard),
    isNestedRow,
    moreActionsOpen,
    openAskBeforeRevoke,
    openMoreActions
  };
};

export default useActions;
