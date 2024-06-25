import { useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';

import { userAtom } from '@centreon/ui-context';

import {
  dashboardToDeleteAtom,
  dashboardToDuplicateAtom,
  isSharesOpenAtom
} from '../../../atoms';
import { Dashboard } from '../../../api/models';
import { useDashboardConfig } from '../DashboardConfig/useDashboardConfig';

import { useDeepMemo } from 'packages/ui/src';

interface Props {
  dashboard: Dashboard;
}

interface useDashboardCardActionsState {
  closeMoreActions: () => void;
  isFavorite?: boolean;
  moreActionsOpen: HTMLElement | null;
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

  const user = useAtomValue(userAtom);
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

  const isFavorite = useDeepMemo({
    deps: [user],
    variable: user.dashboard?.favorites.includes(Number(dashboard.id))
  });

  return {
    closeMoreActions,
    isFavorite,
    moreActionsOpen,
    openDeleteModal,
    openDuplicateModal,
    openEditAccessRightModal,
    openEditModal,
    openMoreActions
  };
};

export default useDashboardCardActions;
