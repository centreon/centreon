import { Dispatch, SetStateAction, startTransition, useState } from 'react';

import { useAtom, useSetAtom } from 'jotai';
import { equals } from 'ramda';

import { Panel, PanelConfiguration } from '../models';
import {
  addPanelDerivedAtom,
  removePanelDerivedAtom,
  setPanelOptionsAndDataDerivedAtom
} from '../atoms';

import { widgetFormInitialDataAtom, widgetPropertiesAtom } from './atoms';
import { Widget } from './models';

interface useWidgetModalState {
  addWidget: (values: Widget) => void;
  askBeforeCloseModal: (shouldAskForClosingConfirmation: boolean) => void;
  askingBeforeCloseModal: boolean;
  closeModal: () => void;
  discardChanges: () => void;
  editWidget: (values: Widget) => void;
  openModal: (widget: Panel | null) => void;
  setAskingBeforeCloseModal: Dispatch<SetStateAction<boolean>>;
  widgetFormInitialData: Widget | null;
}

const useWidgetModal = (): useWidgetModalState => {
  const [askingBeforeCloseModal, setAskingBeforeCloseModal] = useState(false);

  const [widgetFormInitialData, setWidgetFormInitialDataAtom] = useAtom(
    widgetFormInitialDataAtom
  );

  const addPanel = useSetAtom(addPanelDerivedAtom);
  const deletePanel = useSetAtom(removePanelDerivedAtom);
  const setPanelOptions = useSetAtom(setPanelOptionsAndDataDerivedAtom);
  const setWidgetProperties = useSetAtom(widgetPropertiesAtom);

  const openModal = (widget: Panel | null): void =>
    startTransition(() =>
      setWidgetFormInitialDataAtom({
        data: widget?.data || {},
        id: widget?.i || null,
        moduleName: widget?.name || null,
        options: widget?.options || {},
        panelConfiguration: widget?.panelConfiguration || null
      })
    );

  const closeModal = (): void =>
    startTransition(() => {
      setWidgetFormInitialDataAtom(null);
      setWidgetProperties(null);
      setAskingBeforeCloseModal(false);
    });

  const addWidget = (values: Widget): void => {
    const panelConfiguration = values.panelConfiguration as PanelConfiguration;

    addPanel({
      data: values.data || undefined,
      height: panelConfiguration.panelMinHeight,
      moduleName: values.moduleName || '',
      options: values.options,
      panelConfiguration,
      width: panelConfiguration.panelMinWidth
    });
    closeModal();
  };

  const editWidget = (values: Widget): void => {
    if (!equals(values.moduleName, widgetFormInitialData?.moduleName)) {
      const panelConfiguration =
        values.panelConfiguration as PanelConfiguration;

      deletePanel(widgetFormInitialData?.id as string);
      addPanel({
        data: values.data || undefined,
        fixedId: widgetFormInitialData?.id || undefined,
        height: panelConfiguration.panelMinHeight,
        moduleName: values.moduleName || '',
        options: values.options,
        panelConfiguration,
        width: panelConfiguration.panelMinWidth
      });
      closeModal();

      return;
    }

    setPanelOptions({
      data: values.data || undefined,
      id: values.id as string,
      options: values.options
    });
    closeModal();
  };

  const askBeforeCloseModal = (shouldAskForClosingConfirmation): void => {
    if (!shouldAskForClosingConfirmation) {
      closeModal();

      return;
    }
    setAskingBeforeCloseModal(true);
  };

  const discardChanges = (): void => {
    setAskingBeforeCloseModal(false);
    closeModal();
  };

  return {
    addWidget,
    askBeforeCloseModal,
    askingBeforeCloseModal,
    closeModal,
    discardChanges,
    editWidget,
    openModal,
    setAskingBeforeCloseModal,
    widgetFormInitialData
  };
};

export default useWidgetModal;
