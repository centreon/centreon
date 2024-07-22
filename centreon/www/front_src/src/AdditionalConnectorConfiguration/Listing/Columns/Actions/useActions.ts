import { useState } from 'react';

import { useAtom, useSetAtom } from 'jotai';

import { connectorsToDeleteAtom, connectorsToDuplicateAtom } from '../../atom';
import { dialogStateAtom } from '../../../atoms';

interface UseActionsState {
  closeMoreActions: () => void;
  moreActionsOpen: HTMLElement | null;
  openDeleteModal: () => void;
  openDuplicateModal: () => void;
  openEditDialog: () => void;
  openMoreActions: (event) => void;
}

const useActions = (row): UseActionsState => {
  const [moreActionsOpen, setMoreActionsOpen] = useState(null);

  const [dialogState, setDialogState] = useAtom(dialogStateAtom);
  const setConnectorsToDuplicate = useSetAtom(connectorsToDuplicateAtom);
  const setConnectorsToDelete = useSetAtom(connectorsToDeleteAtom);

  const openEditDialog = (): void =>
    setDialogState({
      ...dialogState,
      connector: row,
      isOpen: true,
      variant: 'update'
    });

  const openDuplicateModal = (): void => setConnectorsToDuplicate(row);
  const openDeleteModal = (): void => setConnectorsToDelete(row);

  const openMoreActions = (event): void => setMoreActionsOpen(event.target);
  const closeMoreActions = (): void => setMoreActionsOpen(null);

  return {
    closeMoreActions,
    moreActionsOpen,
    openDeleteModal,
    openDuplicateModal,
    openEditDialog,
    openMoreActions
  };
};

export default useActions;
