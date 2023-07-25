import { startTransition } from 'react';

import { useAtom, useSetAtom } from 'jotai';
import { equals } from 'ramda';

import { Panel, PanelConfiguration } from '../models';
import {
  addPanelDerivedAtom,
  removePanelDerivedAtom,
  setPanelOptionsDerivedAtom
} from '../atoms';

import { widgetFormInitialDataAtom, widgetPropertiesAtom } from './atoms';
import { Widget } from './models';

interface useWidgetModalState {
  addWidget: (values: Widget) => void;
  closeModal: () => void;
  editWidget: (values: Widget) => void;
  openModal: (widget: Panel | null) => void;
  widgetFormInitialData: Widget | null;
}

const useWidgetModal = (): useWidgetModalState => {
  const [widgetFormInitialData, setWidgetFormInitialDataAtom] = useAtom(
    widgetFormInitialDataAtom
  );

  const addPanel = useSetAtom(addPanelDerivedAtom);
  const deletePanel = useSetAtom(removePanelDerivedAtom);
  const setPanelOptions = useSetAtom(setPanelOptionsDerivedAtom);
  const setWidgetProperties = useSetAtom(widgetPropertiesAtom);

  const openModal = (widget: Panel | null): void =>
    startTransition(() =>
      setWidgetFormInitialDataAtom({
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
    });

  const addWidget = (values: Widget): void => {
    const panelConfiguration = values.panelConfiguration as PanelConfiguration;

    addPanel({
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
      id: values.id as string,
      options: values.options
    });
    closeModal();
  };

  return {
    addWidget,
    closeModal,
    editWidget,
    openModal,
    widgetFormInitialData
  };
};

export default useWidgetModal;
