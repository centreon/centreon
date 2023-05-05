import { useEffect, useState } from 'react';

import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';
import { useAtom, useAtomValue } from 'jotai';
import { equals, gt, isNil } from 'ramda';

import { Box, Button } from '@mui/material';

import { Form as FormComponent, useFetchQuery } from '@centreon/ui';

import {
  labelExpandInformationPanel,
  labelReduceInformationPanel
} from '../translatedLabels';
import { panelWidthStorageAtom } from '../atom';

import { basicFormGroups, getInputs } from './inputs';
import { emptyInitialValues, getInitialValues } from './initialValues';
import { submit } from './submit';
import { validationSchema } from './validationSchema';
import Header from './Header';
import { EditedNotificationIdAtom, panelModeAtom } from './atom';
import { PanelMode } from './models';
import { notificationtEndpoint } from './api/endpoints';
import { notificationdecoder } from './api/decoders';

const useStyles = makeStyles()((theme) => ({
  form: {
    padding: theme.spacing(0, 2, 2)
  },
  reducePanel: {
    display: 'flex',
    justifyContent: 'flex-end',
    padding: theme.spacing(1, 2)
  }
}));

const ReducePanel = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const [panelWidth, setPanelWidth] = useAtom(panelWidthStorageAtom);

  const handlePanelWidth = (): void => {
    setPanelWidth((prevState) => (gt(prevState, 675) ? 550 : 800));
  };

  return (
    <Box className={classes.reducePanel}>
      <Button onClick={handlePanelWidth}>
        {gt(panelWidth, 675)
          ? t(labelReduceInformationPanel)
          : t(labelExpandInformationPanel)}
      </Button>
    </Box>
  );
};

const Form = (): JSX.Element => {
  const { classes } = useStyles();

  const [formInitialValues, setFormInitialValues] =
    useState(emptyInitialValues);
  const panelMode = useAtomValue(panelModeAtom);
  const panelWidth = useAtomValue(panelWidthStorageAtom);
  const editedNotificationId = useAtomValue(EditedNotificationIdAtom);

  const { data, isLoading, fetchQuery } = useFetchQuery({
    decoder: notificationdecoder,
    getEndpoint: () => notificationtEndpoint({ id: editedNotificationId }),
    getQueryKey: () => ['notification', editedNotificationId],
    queryOptions: {
      // enabled: false,
      // refetchOnMount: false,
      suspense: false
    }
  });

  useEffect(() => {
    fetchQuery().then(() => {
      if (equals(panelMode, PanelMode.Edit)) {
        setFormInitialValues(getInitialValues(data));
      }
    });
  }, [editedNotificationId]);
  // const formInitialValues = equals(panelMode, PanelMode.Create)
  //   ? emptyInitialValues
  //   : getInitialValues(data);

  // console.log(notificationdecoder.decode(dumyData));
  // console.log(getInitialValues(data));

  return (
    <Box>
      <FormComponent
        Buttons={Box}
        className={classes.form}
        groups={basicFormGroups}
        initialValues={formInitialValues}
        inputs={getInputs({ panelWidth })}
        isLoading={isLoading}
        submit={submit}
        validationSchema={validationSchema}
      >
        <Header />
        <ReducePanel />
      </FormComponent>
    </Box>
  );
};

export default Form;
