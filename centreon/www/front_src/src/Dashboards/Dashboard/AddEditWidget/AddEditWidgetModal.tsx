import { useTranslation } from 'react-i18next';
import { Formik } from 'formik';
import { isNil } from 'ramda';

import { Paper } from '@mui/material';

import { Modal } from '@centreon/ui/components';

import { labelSelectAWidgetType } from '../translatedLabels';

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

  const {
    widgetFormInitialData,
    setAskingBeforeCloseModal,
    addWidget,
    editWidget,
    askBeforeCloseModal,
    askingBeforeCloseModal,
    discardChanges
  } = useWidgetForm();

  const isAddingWidget = isNil(widgetFormInitialData?.id);

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
          fullscreenMarginLeft="48px"
          fullscreenMarginTop="90px"
          size="fullscreen"
          onClose={() => askBeforeCloseModal(dirty)}
        >
          <Modal.Header>{t(labelSelectAWidgetType)}</Modal.Header>
          <>
            <Modal.Body>
              <div className={classes.container}>
                <Paper className={classes.preview}>
                  <Preview />
                </Paper>
                <div className={classes.widgetProperties}>
                  <WidgetSelection />
                  <div className={classes.widgetPropertiesContent}>
                    <WidgetProperties />
                  </div>
                </div>
                <WidgetData />
              </div>
            </Modal.Body>
            <Actions
              closeModal={askBeforeCloseModal}
              isAddingWidget={isAddingWidget}
            />
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
