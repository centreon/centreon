import { useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { isNotNil } from 'ramda';

import {
  dashboardToAddToPlaylistAtom,
  dashboardToDeleteAtom,
  dashboardToDuplicateAtom,
  isSharesOpenAtom
} from '../../../atoms';
import { Dashboard } from '../../../api/models';
import { useDashboardConfig } from '../DashboardConfig/useDashboardConfig';
import { platformVersionsAtom } from '../../../../Main/atoms/platformVersionsAtom';

interface Props {
  dashboard: Dashboard;
}

interface useDashboardCardActionsState {
  closeMoreActions: () => void;
  hasIEEEInstalled: boolean;
  moreActionsOpen: HTMLElement | null;
  openAddToPlaylistModal: () => void;
  openDeleteModal: () => void;
  openDuplicateModal: () => void;
  openEditAccessRightModal: () => void;
  openEditModal: () => void;
  openMoreActions: (event) => void;
}

const useDashboardCardActions = ({
  dashboard
}: Props): useDashboardCardActionsState => {
  const [moreActionsOpen, setMoreActionsOpen] = useState(null);

  const closeMoreActions = (): void => setMoreActionsOpen(null);
  const openMoreActions = (event): void => setMoreActionsOpen(event.target);

  const { editDashboard } = useDashboardConfig();

  const platformVersions = useAtomValue(platformVersionsAtom);
  const setIsSharesOpenAtom = useSetAtom(isSharesOpenAtom);
  const setDashboardToDelete = useSetAtom(dashboardToDeleteAtom);
  const setDashboardToDuplicate = useSetAtom(dashboardToDuplicateAtom);
  const setDashboardToAddToPlaylist = useSetAtom(dashboardToAddToPlaylistAtom);

  const hasIEEEInstalled = isNotNil(
    platformVersions?.modules['centreon-it-edition-extensions']
  );

  const openDeleteModal = (): void => {
    setDashboardToDelete(dashboard);
    closeMoreActions();
  };

  const openDuplicateModal = (): void => {
    setDashboardToDuplicate(dashboard);
    closeMoreActions();
  };

  const openEditModal = (): void => {
    editDashboard(dashboard)();
    closeMoreActions();
  };

  const openEditAccessRightModal = (): void => {
    setIsSharesOpenAtom(dashboard);
  };

  const openAddToPlaylistModal = (): void => {
    setDashboardToAddToPlaylist(dashboard);
  };

  return {
    closeMoreActions,
    hasIEEEInstalled,
    moreActionsOpen,
    openAddToPlaylistModal,
    openDeleteModal,
    openDuplicateModal,
    openEditAccessRightModal,
    openEditModal,
    openMoreActions
  };
};

export default useDashboardCardActions;
