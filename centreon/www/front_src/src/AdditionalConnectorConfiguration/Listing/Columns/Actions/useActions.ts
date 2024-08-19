import { useAtom, useSetAtom } from 'jotai';

import { connectorsToDeleteAtom } from '../../atom';
import { dialogStateAtom } from '../../../atoms';

interface UseActionsState {
  openDeleteModal: () => void;
  openEditDialog: () => void;
}

const useActions = (row): UseActionsState => {
  const [dialogState, setDialogState] = useAtom(dialogStateAtom);
  const setConnectorsToDelete = useSetAtom(connectorsToDeleteAtom);

  const openEditDialog = (): void =>
    setDialogState({
      ...dialogState,
      connector: row,
      isOpen: true,
      variant: 'update'
    });

  const openDeleteModal = (): void => setConnectorsToDelete(row);

  return {
    openDeleteModal,
    openEditDialog
  };
};

export default useActions;
