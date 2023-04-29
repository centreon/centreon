import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';
import { useAtom } from 'jotai';
import { equals, gt } from 'ramda';

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
import { panelModeAtom } from './atom';
import { PanelMode } from './models';
import { notificationtEndpoint } from './api/endpoints';
import { notificationdecoder } from './api/decoders';
import { data as dumyData } from './api/dummyData';

const useStyles = makeStyles()((theme) => ({
  container: {
    boxSizing: 'border-box',
    padding: theme.spacing(0, 2, 2)
  },
  reducePanel: {
    display: 'flex',
    justifyContent: 'flex-end'
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
  const [panelMode] = useAtom(panelModeAtom);
  const [panelWidth] = useAtom(panelWidthStorageAtom);

  const { data, isLoading } = useFetchQuery({
    decoder: notificationdecoder,
    getEndpoint: () => notificationtEndpoint({ id: 1 }),
    getQueryKey: () => ['notification']
  });

  const formInitialValues = equals(panelMode, PanelMode.Create)
    ? emptyInitialValues
    : getInitialValues(data);

  // console.log(notificationdecoder.decode(dumyData));
  // console.log(getInitialValues(data));

  return (
    <Box className={classes.container}>
      <FormComponent
        Buttons={Box}
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
