import { useTranslation } from 'react-i18next';
import { Formik } from 'formik';
import { isNil } from 'ramda';

import { Paper } from '@mui/material';

import { Modal } from '@centreon/ui/components';

import { labelAddWidget, labelEditWidget } from '../translatedLabels';
import Title from '../../components/Title';

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
          fullscreenMargins={{
            left: 48,
            top: 90
          }}
          size="fullscreen"
          onClose={() => askBeforeCloseModal(dirty)}
        >
          <Modal.Header>
            <Title>
              {t(isAddingWidget ? labelAddWidget : labelEditWidget)}
            </Title>
          </Modal.Header>
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
