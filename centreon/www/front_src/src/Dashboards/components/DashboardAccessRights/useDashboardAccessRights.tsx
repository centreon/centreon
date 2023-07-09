import { useMemo } from 'react';

import { atom, useAtom } from 'jotai';

import { Dashboard } from '../../api/models';
import routeMap from '../../../reactRoutes/routeMap';

const dialogStateAtom = atom<{
  dashboard: Dashboard | null;
  isOpen: boolean;
  status: 'idle' | 'loading' | 'success' | 'error';
}>({
  dashboard: null,
  isOpen: false,
  status: 'idle'
});

type UseDashboardAccessRights = {
  closeDialog: () => void;
  dashboard: Dashboard | null;
  editAccessRights: (dashboard: Dashboard) => () => void;
  isDialogOpen: boolean;
  resourceLink: string;
  status: 'idle' | 'loading' | 'success' | 'error';
  submit: (dashboard: Dashboard) => void;
};

const useDashboardAccessRights = (): UseDashboardAccessRights => {
  const [dialogState, setDialogState] = useAtom(dialogStateAtom);

  const closeDialog = (): void =>
    setDialogState({ ...dialogState, isOpen: false });

  const editAccessRights = (dashboard: Dashboard) => (): void => {
    setDialogState({
      ...dialogState,
      dashboard,
      isOpen: true
    });
  };

  const resourceLink = useMemo(() => {
    const path = routeMap.dashboard.replace(
      ':dashboardId',
      (dialogState.dashboard?.id as string) ?? ''
    );

    return `${window.location.origin}${path}`;
  }, [dialogState.dashboard]);

  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const submit = async (dashboard: Dashboard): Promise<void> => {
    setDialogState({ ...dialogState, isOpen: false });
  };

  return {
    closeDialog,
    dashboard: dialogState.dashboard,
    editAccessRights,
    isDialogOpen: dialogState.isOpen,
    resourceLink,
    status: dialogState.status,
    submit
  };
};

export { useDashboardAccessRights };
