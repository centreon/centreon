import { useTranslation } from 'react-i18next';
import { useAtom, useAtomValue } from 'jotai';
import { equals, gt } from 'ramda';

import { Box, Button } from '@mui/material';

import { Form as FormComponent, useFetchQuery } from '@centreon/ui';

import {
  labelExpandInformationPanel,
  labelReduceInformationPanel
} from '../translatedLabels';
import { panelWidthStorageAtom } from '../atom';

import useStyles from './Form.styles';
import useFormInputs from './useFormInputs';
import { emptyInitialValues, getInitialValues } from './initialValues';
import useValidationSchema from './validationSchema';
import Header from './Header';
import { EditedNotificationIdAtom, panelModeAtom } from './atom';
import { PanelMode } from './models';
import { notificationtEndpoint } from './api/endpoints';
import { notificationdecoder } from './api/decoders';

const ReducePanel = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const [panelWidth, setPanelWidth] = useAtom(panelWidthStorageAtom);

  const handlePanelWidth = (): void => {
    setPanelWidth((prevState) => (gt(prevState, 675) ? 550 : 800));
  };

  const panelWidthLabel = gt(panelWidth, 675)
    ? t(labelReduceInformationPanel)
    : t(labelExpandInformationPanel);

  return (
    <Box className={classes.reducePanel}>
      <Button className={classes.reducePanelButton} onClick={handlePanelWidth}>
        {panelWidthLabel}
      </Button>
    </Box>
  );
};

const Form = (): JSX.Element => {
  const { classes } = useStyles();

  const panelMode = useAtomValue(panelModeAtom);
  const panelWidth = useAtomValue(panelWidthStorageAtom);
  const editedNotificationId = useAtomValue(EditedNotificationIdAtom);

  const { inputs, basicFormGroups } = useFormInputs({ panelWidth });
  const { validationSchema } = useValidationSchema();

  const { data, isLoading } = useFetchQuery({
    decoder: notificationdecoder,
    getEndpoint: () => notificationtEndpoint({ id: editedNotificationId }),
    getQueryKey: () => ['notification', editedNotificationId],
    queryOptions: {
      enabled: equals(panelMode, PanelMode.Edit),
      suspense: false
    }
  });

  const initialValues =
    equals(panelMode, PanelMode.Edit) && data
      ? getInitialValues(data)
      : emptyInitialValues;

  const loading = equals(panelMode, PanelMode.Edit) ? isLoading : false;

  return (
    <Box>
      <FormComponent
        isCollapsible
        Buttons={Box}
        className={classes.form}
        groups={basicFormGroups}
        groupsClassName={classes.groups}
        initialValues={initialValues}
        inputs={inputs}
        isLoading={loading}
        validationSchema={validationSchema}
      >
        <>
          <Header />
          <ReducePanel />
        </>
      </FormComponent>
    </Box>
  );
};

export default Form;
