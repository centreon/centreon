import { startTransition } from 'react';

import { useAtom, useSetAtom } from 'jotai';

import { askDeletePanelAtom, removePanelDerivedAtom } from '../atoms';

interface UseDeleteWidgetModalState {
  closeModal: () => void;
  deleteWidget: () => void;
  isModalOpened: boolean;
}

const useDeleteWidgetModal = (): UseDeleteWidgetModalState => {
  const [askDeletePanel, setAskDeletePanel] = useAtom(askDeletePanelAtom);
  const removePanel = useSetAtom(removePanelDerivedAtom);

  const closeModal = (): void => startTransition(() => setAskDeletePanel(null));

  const deleteWidget = (): void => {
    if (!askDeletePanel) {
      return;
    }

    removePanel(askDeletePanel);
    closeModal();
  };

  return {
    closeModal,
    deleteWidget,
    isModalOpened: Boolean(askDeletePanel)
  };
};

export default useDeleteWidgetModal;
