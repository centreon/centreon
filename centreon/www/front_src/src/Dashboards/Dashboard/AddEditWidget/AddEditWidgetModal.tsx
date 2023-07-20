import { useTranslation } from 'react-i18next';
import { Formik } from 'formik';
import { isNil } from 'ramda';

import { Paper } from '@mui/material';

import { Modal } from '@centreon/ui/components';

import { labelSelectAWidgetType } from '../translatedLabels';

import useWidgetForm from './useWidgetModal';
import { useAddWidgetStyles } from './addWidget.styles';
import { Widget } from './models';
import { Preview, WidgetProperties, WidgetSelection } from './WidgetProperties';
import Actions from './Actions';

const AddWidgetModal = (): JSX.Element => {
  const { t } = useTranslation();

  const { classes } = useAddWidgetStyles();

  const { widgetFormInitialData, closeModal, addWidget, editWidget } =
    useWidgetForm();

  const isAddingWidget = isNil(widgetFormInitialData?.id);

  return (
    <Modal
      open={Boolean(widgetFormInitialData)}
      size="xlarge"
      onClose={closeModal}
    >
      <Modal.Header>{t(labelSelectAWidgetType)}</Modal.Header>
      <Formik<Widget>
        initialValues={widgetFormInitialData as Widget}
        onSubmit={isAddingWidget ? addWidget : editWidget}
      >
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
            </div>
          </Modal.Body>
          <Actions closeModal={closeModal} isAddingWidget={isAddingWidget} />
        </>
      </Formik>
    </Modal>
  );
};

export default AddWidgetModal;
