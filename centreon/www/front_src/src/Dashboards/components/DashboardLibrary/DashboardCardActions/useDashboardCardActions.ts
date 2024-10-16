import { useMemo, useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';

import { profileAtom } from '@centreon/ui-context';
import { Dashboard } from '../../../api/models';
import {
  dashboardToDeleteAtom,
  dashboardToDuplicateAtom,
  isSharesOpenAtom
} from '../../../atoms';
import { useDashboardConfig } from '../DashboardConfig/useDashboardConfig';

interface Props {
  dashboard: Dashboard;
}

interface UseDashboardCardActionsState {
  closeMoreActions: () => void;
  moreActionsOpen: HTMLElement | null;
  openDeleteModal: () => void;
  openDuplicateModal: () => void;
  openEditAccessRightModal: () => void;
  openEditModal: () => void;
  openMoreActions: (event) => void;
  isFavorite?: boolean;
}

const useDashboardCardActions = ({
  dashboard
}: Props): UseDashboardCardActionsState => {
  const [moreActionsOpen, setMoreActionsOpen] = useState(null);

  const closeMoreActions = (): void => setMoreActionsOpen(null);
  const openMoreActions = (event): void => setMoreActionsOpen(event.target);

  const { editDashboard } = useDashboardConfig();

  const profile = useAtomValue(profileAtom);
  const setIsSharesOpenAtom = useSetAtom(isSharesOpenAtom);
  const setDashboardToDelete = useSetAtom(dashboardToDeleteAtom);
  const setDashboardToDuplicate = useSetAtom(dashboardToDuplicateAtom);

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

  const isFavorite = useMemo(
    () => profile?.favoriteDashboards?.includes(Number(dashboard?.id)),
    [profile, dashboard?.id]
  );

  return {
    closeMoreActions,
    moreActionsOpen,
    openDeleteModal,
    openDuplicateModal,
    openEditAccessRightModal,
    openEditModal,
    openMoreActions,
    isFavorite
  };
};

export default useDashboardCardActions;
