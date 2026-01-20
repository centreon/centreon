import { Paper, useMediaQuery, useTheme } from '@mui/material';

import { Modal } from '@centreon/ui/components';

import { Formik } from 'formik';
import { useAtomValue } from 'jotai';
import { isNil } from 'ramda';
import { useCallback, useEffect } from 'react';
import { useTranslation } from 'react-i18next';

import { isSidebarOpenAtom } from '../../../../Navigation/navigationAtoms';
import { useCanEditProperties } from '../hooks/useCanEditDashboard';
import {
  labelAddWidget,
  labelEditWidget,
  labelViewWidgetProperties
} from '../translatedLabels';
import Actions from './Actions';
import { useAddWidgetStyles } from './addWidget.styles';
import type { Widget } from './models';
import UnsavedChanges from './UnsavedChanges';
import useValidationSchema from './useValidationSchema';
import useWidgetForm from './useWidgetModal';
import {
  Preview,
  WidgetData,
  WidgetMessage,
  WidgetProperties,
  WidgetSelection
} from './WidgetProperties';

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

  const theme = useTheme();
  const isSmallDisplay = useMediaQuery(theme.breakpoints.down('sm'));

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
      initialValues={widgetFormInitialData as Widget}
      onSubmit={isAddingWidget ? addWidget : editWidget}
      validateOnBlur
      validateOnChange
      validationSchema={schema}
    >
      {({ dirty }) => (
        <Modal
          fullscreenMargins={{
            left: isSidebarOpen ? 165 : 48,
            top: 90
          }}
          onClose={() => askBeforeCloseModal(dirty)}
          open
          size="fullscreen"
        >
          <Modal.Header variant="h6">{t(getTitle())}</Modal.Header>

          <Modal.Body>
            {isSmallDisplay ? (
              <div className={classes.smallContainer}>
                <WidgetSelection />
                <Paper className={classes.preview}>
                  <Preview />
                </Paper>
                <div className={classes.smallWidgetProperties}>
                  <WidgetProperties />
                </div>
                <WidgetData />
              </div>
            ) : (
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
                  <WidgetMessage />
                </div>
              ) : (
                <div className={classes.container}>
                  <div className={classes.widgetProperties}>
                    <WidgetSelection />
                    <div className={classes.widgetPropertiesContentContainer}>
                      <div className={classes.widgetPropertiesContent}>
                        <WidgetProperties />
                      </div>
                    </div>
                  </div>
                  <div className="grid grid-rows-[360px_1fr]">
                    <Paper className={classes.preview}>
                      <Preview />
                    </Paper>
                    <WidgetData />
                    <WidgetMessage />
                  </div>
                </div>
              )}
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
