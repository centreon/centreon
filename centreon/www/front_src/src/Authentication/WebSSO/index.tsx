import { useMemo } from 'react';

import { useTranslation } from 'react-i18next';
import { isNil, not } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { LinearProgress } from '@mui/material';

import useTab from '../useTab';
import FormTitle from '../FormTitle';
import useLoadConfiguration from '../shared/useLoadConfiguration';
import { Provider } from '../models';
import { webSSOConfigurationDecoder } from '../api/decoders';

import { labelDefineWebSSOConfiguration } from './translatedLabels';
import WebSSOForm from './Form';
import { WebSSOConfiguration } from './models';

const useStyles = makeStyles()((theme) => ({
  container: {
    width: 'fit-content'
  },
  loading: {
    height: theme.spacing(0.5)
  },
  paper: {
    padding: theme.spacing(2)
  }
}));

const WebSSOConfigurationForm = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { loadConfiguration, initialConfiguration, sendingGetConfiguration } =
    useLoadConfiguration<WebSSOConfiguration>({
      decoder: webSSOConfigurationDecoder,
      providerType: Provider.WebSSO
    });

  const isWebSSOConfigurationEmpty = useMemo(
    () => isNil(initialConfiguration),
    [initialConfiguration]
  );

  useTab(isWebSSOConfigurationEmpty);

  return (
    <div>
      <FormTitle title={t(labelDefineWebSSOConfiguration)} />
      <div className={classes.loading}>
        {not(isWebSSOConfigurationEmpty) && sendingGetConfiguration && (
          <LinearProgress />
        )}
      </div>
      <WebSSOForm
        initialValues={initialConfiguration as WebSSOConfiguration}
        isLoading={isWebSSOConfigurationEmpty}
        loadWebSSOonfiguration={loadConfiguration}
      />
    </div>
  );
};

export default WebSSOConfigurationForm;
