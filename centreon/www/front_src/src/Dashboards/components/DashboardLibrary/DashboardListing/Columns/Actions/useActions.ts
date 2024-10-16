import { useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { isNil } from 'ramda';

import { useDeepMemo } from '@centreon/ui';
import { profileAtom } from '@centreon/ui-context';
import {
  dashboardToDeleteAtom,
  dashboardToDuplicateAtom,
  isSharesOpenAtom
} from '../../../../../atoms';
import { useDashboardConfig } from '../../../DashboardConfig/useDashboardConfig';
import { askBeforeRevokeAtom } from '../../atom';
import { unformatDashboard } from '../../utils';

interface UseActionsState {
  closeMoreActions: () => void;
  editAccessRights: () => void;
  editDashboard: () => void;
  isNestedRow: boolean;
  moreActionsOpen: HTMLElement | null;
  openAskBeforeRevoke: () => void;
  openDeleteModal: () => void;
  openDuplicateModal: () => void;
  openMoreActions: (event) => void;
  isFavorite?: boolean;
}

const useActions = (row): UseActionsState => {
  const [moreActionsOpen, setMoreActionsOpen] = useState(null);
  const setAskBeforeRevoke = useSetAtom(askBeforeRevokeAtom);

  const profile = useAtomValue(profileAtom);

  const { editDashboard } = useDashboardConfig();
  const setIsSharesOpen = useSetAtom(isSharesOpenAtom);

  const seDashboardToDuplicate = useSetAtom(dashboardToDuplicateAtom);
  const seDashboardToDelete = useSetAtom(dashboardToDeleteAtom);

  const openDuplicateModal = (): void =>
    seDashboardToDuplicate(unformatDashboard(row));

  const openDeleteModal = (): void =>
    seDashboardToDelete(unformatDashboard(row));

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

  const isFavorite = useDeepMemo({
    deps: [profile],
    variable: profile?.favoriteDashboards?.includes(row.id)
  });

  return {
    closeMoreActions,
    editAccessRights: openShares(unformattedDashboard),
    editDashboard: editDashboard(unformattedDashboard),
    isNestedRow,
    moreActionsOpen,
    openAskBeforeRevoke,
    openDeleteModal,
    openDuplicateModal,
    openMoreActions,
    isFavorite
  };
};

export default useActions;
