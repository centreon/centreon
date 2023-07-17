import { startTransition } from 'react';

import { useAtom, useSetAtom } from 'jotai';

import { addPanelDerivedAtom } from '../atoms';

import { isAddWidgetModalOpenedAtom } from './atoms';

import { FederatedComponentsConfiguration } from 'www/front_src/src/federatedModules/models';

interface UseAddWidgetState {
  addWidget: (
    federatedComponentConfiguration: FederatedComponentsConfiguration
  ) => void;
  closeModal: () => void;
  isAddWidgetModalOpened: boolean;
  openModal: () => void;
}

const useAddWidget = (): UseAddWidgetState => {
  const [isAddWidgetModalOpened, setOpenAddWidgetModal] = useAtom(
    isAddWidgetModalOpenedAtom
  );

  const addPanel = useSetAtom(addPanelDerivedAtom);

  const openModal = (): void =>
    startTransition(() => setOpenAddWidgetModal(true));

  const closeModal = (): void =>
    startTransition(() => setOpenAddWidgetModal(false));

  const addWidget = (
    federatedComponentConfiguration: FederatedComponentsConfiguration
  ): void => {
    addPanel({
      height: federatedComponentConfiguration.panelMinHeight,
      options: {},
      panelConfiguration: federatedComponentConfiguration,
      width: federatedComponentConfiguration.panelMinWidth
    });
    closeModal();
  };

  return {
    addWidget,
    closeModal,
    isAddWidgetModalOpened,
    openModal
  };
};

export default useAddWidget;
