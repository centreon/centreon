import { useEffect } from 'react';

import { atom, useAtom } from 'jotai';

import { Dashboard } from '../../api/models';
import { useDeleteDashboard } from '../../api/useDeleteDashboard';

const dialogStateAtom = atom<{
  dashboard: Dashboard | null;
  isOpen: boolean;
  status: 'idle' | 'loading' | 'success' | 'error';
}>({
  dashboard: null,
  isOpen: false,
  status: 'idle'
});

type UseDashboardForm = {
  closeDialog: () => void;
  dashboard: Dashboard | null;
  deleteDashboard: (dashboard: Dashboard) => () => void;
  isDialogOpen: boolean;
  status: 'idle' | 'loading' | 'success' | 'error';
  submit: (dashboard: Dashboard) => void;
};

const useDashboardDelete = (): UseDashboardForm => {
  const [dialogState, setDialogState] = useAtom(dialogStateAtom);

  const {
    mutate: deleteDashboardMutation,
    reset: resetDeleteMutation,
    status: statusDeleteMutation
  } = useDeleteDashboard();

  const closeDialog = (): void =>
    setDialogState({ ...dialogState, isOpen: false });

  const deleteDashboard = (dashboard: Dashboard) => (): void => {
    setDialogState({
      ...dialogState,
      dashboard,
      isOpen: true
    });
  };

  const submit = async (dashboard: Dashboard): Promise<void> => {
    setDialogState({ ...dialogState, isOpen: false });

    await deleteDashboardMutation(dashboard);
  };

  useEffect(() => {
    setDialogState({ ...dialogState, status: statusDeleteMutation });

    if (statusDeleteMutation === 'success') resetDeleteMutation();
  }, [statusDeleteMutation]);

  return {
    closeDialog,
    dashboard: dialogState.dashboard,
    deleteDashboard,
    isDialogOpen: dialogState.isOpen,
    status: dialogState.status,
    submit
  };
};

export { useDashboardDelete };
