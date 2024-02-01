import { useCallback, useEffect } from 'react';

import { useTranslation } from 'react-i18next';
import { Formik } from 'formik';
import { isNil } from 'ramda';
import { useAtomValue } from 'jotai';

import { Paper } from '@mui/material';

import { Modal } from '@centreon/ui/components';

import {
  labelAddWidget,
  labelEditWidget,
  labelViewWidgetProperties
} from '../translatedLabels';
import Title from '../../../components/Title';
import { useCanEditProperties } from '../hooks/useCanEditDashboard';
import { isSidebarOpenAtom } from '../../../../Navigation/navigationAtoms';

import useWidgetForm from './useWidgetModal';
import { useAddWidgetStyles } from './addWidget.styles';
import { Widget } from './models';
import {
  Preview,
  WidgetData,
  WidgetProperties,
  WidgetSelection
} from './WidgetProperties';
import Actions from './Actions';
import useValidationSchema from './useValidationSchema';
import UnsavedChanges from './UnsavedChanges';

const AddWidgetModal = (): JSX.Element | null => {
  const { t } = useTranslation();

  const { schema } = useValidationSchema();

  const { classes } = useAddWidgetStyles();

  const { canEditField } = useCanEditProperties();

  const isSidebarOpen = useAtomValue(isSidebarOpenAtom);

  const {
    widgetFormInitialData,
    setAskingBeforeCloseModal,
    addWidget,
    editWidget,
    askBeforeCloseModal,
    askingBeforeCloseModal,
    discardChanges,
    closeModal
  } = useWidgetForm();

  const isAddingWidget = isNil(widgetFormInitialData?.id);

  const getTitle = useCallback((): string => {
    if (!isAddingWidget && !canEditField) {
      return labelViewWidgetProperties;
    }

    return isAddingWidget ? labelAddWidget : labelEditWidget;
  }, [canEditField, isAddingWidget]);

  useEffect(() => {
    return () => {
      closeModal();
    };
  }, []);

  if (!widgetFormInitialData) {
    return null;
  }

  return (
    <Formik<Widget>
      validateOnBlur
      validateOnChange
      initialValues={widgetFormInitialData as Widget}
      validationSchema={schema}
      onSubmit={isAddingWidget ? addWidget : editWidget}
    >
      {({ dirty }) => (
        <Modal
          open
          fullscreenMargins={{
            left: isSidebarOpen ? 165 : 48,
            top: 90
          }}
          size="fullscreen"
          onClose={() => askBeforeCloseModal(dirty)}
        >
          <Modal.Header>
            <Title>{t(getTitle())}</Title>
          </Modal.Header>
          <>
            <Modal.Body>
              <div className={classes.container}>
                <div className={classes.widgetProperties}>
                  <WidgetSelection />
                  <div className={classes.widgetPropertiesContentContainer}>
                    <div className={classes.widgetPropertiesContent}>
                      <WidgetProperties />
                    </div>
                  </div>
                </div>
                <div>
                  <Paper className={classes.preview}>
                    <Preview />
                  </Paper>
                  <WidgetData />
                </div>
              </div>
            </Modal.Body>
            <Actions closeModal={askBeforeCloseModal} />
            <UnsavedChanges
              closeDialog={() => setAskingBeforeCloseModal(false)}
              discard={discardChanges}
              opened={askingBeforeCloseModal}
            />
          </>
        </Modal>
      )}
    </Formik>
  );
};

export default AddWidgetModal;
