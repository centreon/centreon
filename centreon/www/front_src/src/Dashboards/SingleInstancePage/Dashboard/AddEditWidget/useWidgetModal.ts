import { Dispatch, SetStateAction, startTransition, useState } from 'react';

import { useAtom, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useSnackbar } from '@centreon/ui';

import { Panel, PanelConfiguration } from '../models';
import {
  addPanelDerivedAtom,
  removePanelDerivedAtom,
  setPanelOptionsAndDataDerivedAtom
} from '../atoms';
import {
  labelYourWidgetHasBeenCreated,
  labelYourWidgetHasBeenModified
} from '../translatedLabels';
import { useCanEditProperties } from '../hooks/useCanEditDashboard';

import {
  customBaseColorAtom,
  singleHostPerMetricAtom,
  singleMetricSelectionAtom,
  widgetFormInitialDataAtom,
  widgetPropertiesAtom
} from './atoms';
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
  const { t } = useTranslation();

  const { canEditField } = useCanEditProperties();

  const [askingBeforeCloseModal, setAskingBeforeCloseModal] = useState(false);

  const [widgetFormInitialData, setWidgetFormInitialDataAtom] = useAtom(
    widgetFormInitialDataAtom
  );

  const addPanel = useSetAtom(addPanelDerivedAtom);
  const deletePanel = useSetAtom(removePanelDerivedAtom);
  const setPanelOptions = useSetAtom(setPanelOptionsAndDataDerivedAtom);
  const setWidgetProperties = useSetAtom(widgetPropertiesAtom);
  const setSingleMetricSection = useSetAtom(singleMetricSelectionAtom);
  const setSingleHostPerMetric = useSetAtom(singleHostPerMetricAtom);
  const setCustomBaseColor = useSetAtom(customBaseColorAtom);

  const { showSuccessMessage } = useSnackbar();

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
      setWidgetProperties(undefined);
      setAskingBeforeCloseModal(false);
      setSingleMetricSection(undefined);
      setSingleHostPerMetric(undefined);
      setCustomBaseColor(undefined);
    });

  const addWidget = (values: Widget): void => {
    const panelConfiguration = values.panelConfiguration as PanelConfiguration;

    addPanel({
      data: values.data || undefined,
      moduleName: values.moduleName || '',
      options: values.options,
      panelConfiguration
    });
    showSuccessMessage(t(labelYourWidgetHasBeenCreated));
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
        moduleName: values.moduleName || '',
        options: values.options,
        panelConfiguration
      });
      showSuccessMessage(t(labelYourWidgetHasBeenModified));
      closeModal();

      return;
    }

    setPanelOptions({
      data: values.data || undefined,
      id: widgetFormInitialData?.id as string,
      options: values.options
    });
    showSuccessMessage(t(labelYourWidgetHasBeenModified));
    closeModal();
  };

  const askBeforeCloseModal = (shouldAskForClosingConfirmation): void => {
    if (!shouldAskForClosingConfirmation || !canEditField) {
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
