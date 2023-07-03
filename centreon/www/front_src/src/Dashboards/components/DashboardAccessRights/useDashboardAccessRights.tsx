import { atom, useAtom } from 'jotai';

import { Dashboard } from '../../api/models';

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

  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const submit = async (dashboard: Dashboard): Promise<void> => {
    setDialogState({ ...dialogState, isOpen: false });
  };

  return {
    closeDialog,
    dashboard: dialogState.dashboard,
    editAccessRights,
    isDialogOpen: dialogState.isOpen,
    status: dialogState.status,
    submit
  };
};

export { useDashboardAccessRights };
