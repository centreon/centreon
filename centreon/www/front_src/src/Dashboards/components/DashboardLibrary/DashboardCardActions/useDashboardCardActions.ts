import { useState } from 'react';

import { useSetAtom } from 'jotai';

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
}

const useDashboardCardActions = ({
  dashboard
}: Props): UseDashboardCardActionsState => {
  const [moreActionsOpen, setMoreActionsOpen] = useState(null);

  const closeMoreActions = (): void => setMoreActionsOpen(null);
  const openMoreActions = (event): void => setMoreActionsOpen(event.target);

  const { editDashboard } = useDashboardConfig();

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



  return {
    closeMoreActions,
    moreActionsOpen,
    openDeleteModal,
    openDuplicateModal,
    openEditAccessRightModal,
    openEditModal,
    openMoreActions,
  };
};

export default useDashboardCardActions;
