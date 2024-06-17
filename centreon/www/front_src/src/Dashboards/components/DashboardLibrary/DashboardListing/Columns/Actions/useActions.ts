import { useState } from 'react';

import { isNil, isNotNil } from 'ramda';
import { useAtomValue, useSetAtom } from 'jotai';

import { useDashboardConfig } from '../../../DashboardConfig/useDashboardConfig';
import { unformatDashboard } from '../../utils';
import { askBeforeRevokeAtom } from '../../atom';
import {
  dashboardToAddToPlaylistAtom,
  dashboardToDeleteAtom,
  dashboardToDuplicateAtom,
  isSharesOpenAtom
} from '../../../../../atoms';
import { platformVersionsAtom } from '../../../../../../Main/atoms/platformVersionsAtom';

interface UseActionsState {
  closeMoreActions: () => void;
  editAccessRights: () => void;
  editDashboard: () => void;
  hasIEEEInstalled: boolean;
  isNestedRow: boolean;
  moreActionsOpen: HTMLElement | null;
  openAddToPlaylistModal: () => void;
  openAskBeforeRevoke: () => void;
  openDeleteModal: () => void;
  openDuplicateModal: () => void;
  openMoreActions: (event) => void;
}

const useActions = (row): UseActionsState => {
  const [moreActionsOpen, setMoreActionsOpen] = useState(null);

  const { editDashboard } = useDashboardConfig();

  const platformVersions = useAtomValue(platformVersionsAtom);
  const setAskBeforeRevoke = useSetAtom(askBeforeRevokeAtom);
  const setIsSharesOpen = useSetAtom(isSharesOpenAtom);
  const seDashboardToDuplicate = useSetAtom(dashboardToDuplicateAtom);
  const seDashboardToDelete = useSetAtom(dashboardToDeleteAtom);
  const setDashboardToAddToPlaylist = useSetAtom(dashboardToAddToPlaylistAtom);

  const openDuplicateModal = (): void =>
    seDashboardToDuplicate(unformatDashboard(row));

  const openDeleteModal = (): void =>
    seDashboardToDelete(unformatDashboard(row));

  const openMoreActions = (event): void => setMoreActionsOpen(event.target);
  const closeMoreActions = (): void => setMoreActionsOpen(null);
  const openShares = (dashboard) => () => setIsSharesOpen(dashboard);

  const isNestedRow = !isNil(row?.role);

  const unformattedDashboard = isNestedRow ? row : unformatDashboard(row);

  const hasIEEEInstalled = isNotNil(
    platformVersions?.modules['centreon-it-edition-extensions']
  );

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

  const openAddToPlaylistModal = (): void => {
    setDashboardToAddToPlaylist(unformattedDashboard);
  };

  return {
    closeMoreActions,
    editAccessRights: openShares(unformattedDashboard),
    editDashboard: editDashboard(unformattedDashboard),
    hasIEEEInstalled,
    isNestedRow,
    moreActionsOpen,
    openAddToPlaylistModal,
    openAskBeforeRevoke,
    openDeleteModal,
    openDuplicateModal,
    openMoreActions
  };
};

export default useActions;
