import { useEffect } from 'react';

import { atom, useAtom } from 'jotai';
import {
  createSearchParams,
  generatePath,
  useNavigate
} from 'react-router-dom';

import { Dashboard, isDashboard } from '../../api/models';
import { useCreateDashboard } from '../../api/useCreateDashboard';
import routeMap from '../../../reactRoutes/routeMap';
import { useUpdateDashboard } from '../../api/useUpdateDashboard';

const dialogStateAtom = atom<{
  dashboard: Dashboard | null;
  isOpen: boolean;
  status: 'idle' | 'loading' | 'success' | 'error';
  variant: 'create' | 'update';
}>({
  dashboard: null,
  isOpen: false,
  status: 'idle',
  variant: 'create'
});

type UseDashboardConfig = {
  closeDialog: () => void;
  createDashboard: () => void;
  dashboard: Dashboard | null;
  editDashboard: (dashboard: Dashboard) => () => void;
  isDialogOpen: boolean;
  status: 'idle' | 'loading' | 'success' | 'error';
  submit: (dashboard: Dashboard) => void;
  variant: 'create' | 'update';
};

const useDashboardConfig = (): UseDashboardConfig => {
  const [dialogState, setDialogState] = useAtom(dialogStateAtom);

  const {
    mutate: createDashboardMutation,
    reset: resetCreateMutation,
    status: statusCreateMutation
  } = useCreateDashboard();

  const {
    mutate: updateDashboardMutation,
    reset: resetUpdateMutation,
    status: statusUpdateMutation
  } = useUpdateDashboard();

  const closeDialog = (): void =>
    setDialogState({ ...dialogState, isOpen: false });

  const createDashboard = (): void => {
    setDialogState({
      ...dialogState,
      dashboard: null,
      isOpen: true,
      variant: 'create'
    });
  };

  const editDashboard = (dashboard: Dashboard) => (): void => {
    setDialogState({
      ...dialogState,
      dashboard,
      isOpen: true,
      variant: 'update'
    });
  };

  const navigate = useNavigate();
  const navigateToDashboard = (dashboardId: string | number): void =>
    navigate({
      pathname: generatePath(routeMap.dashboard, { dashboardId }),
      search: createSearchParams({ view: 'edit' }).toString()
    });

  const submit = async (dashboard: Dashboard): Promise<void> => {
    setDialogState({ ...dialogState, isOpen: false });

    const data =
      dialogState.variant === 'create'
        ? await createDashboardMutation(dashboard)
        : await updateDashboardMutation(dashboard);

    if (isDashboard(data) && dialogState.variant === 'create')
      navigateToDashboard(data.id);
  };

  useEffect(() => {
    if (statusCreateMutation !== 'idle')
      setDialogState({ ...dialogState, status: statusCreateMutation });

    if (statusUpdateMutation !== 'idle')
      setDialogState({ ...dialogState, status: statusUpdateMutation });

    if (statusCreateMutation === 'success') resetCreateMutation();
    if (statusUpdateMutation === 'success') resetUpdateMutation();
  }, [statusCreateMutation, statusUpdateMutation]);

  return {
    closeDialog,
    createDashboard,
    dashboard: dialogState.dashboard,
    editDashboard,
    isDialogOpen: dialogState.isOpen,
    status: dialogState.status,
    submit,
    variant: dialogState.variant
  };
};

export { useDashboardConfig };
