import { useState } from 'react';

import { useSetAtom } from 'jotai';

import { connectorsToDeleteAtom, connectorsToDuplicateAtom } from '../../atom';

interface UseActionsState {
  closeMoreActions: () => void;
  editConnectorConfiguration: () => void;
  moreActionsOpen: HTMLElement | null;
  openDeleteModal: () => void;
  openDuplicateModal: () => void;
  openMoreActions: (event) => void;
}

const useActions = (row): UseActionsState => {
  const [moreActionsOpen, setMoreActionsOpen] = useState(null);

  const setConnectorsToDuplicate = useSetAtom(connectorsToDuplicateAtom);
  const setConnectorsToDelete = useSetAtom(connectorsToDeleteAtom);

  const openDuplicateModal = (): void => setConnectorsToDuplicate(row);
  const openDeleteModal = (): void => setConnectorsToDelete(row);

  const openMoreActions = (event): void => setMoreActionsOpen(event.target);
  const closeMoreActions = (): void => setMoreActionsOpen(null);

  const editConnectorConfiguration = (): void => undefined;

  return {
    closeMoreActions,
    editConnectorConfiguration,
    moreActionsOpen,
    openDeleteModal,
    openDuplicateModal,
    openMoreActions
  };
};

export default useActions;
