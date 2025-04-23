import { isNil, not } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { LinearProgress } from '@mui/material';

import FormTitle from '../FormTitle';
import { webSSOConfigurationDecoder } from '../api/decoders';
import { Provider } from '../models';
import useLoadConfiguration from '../shared/useLoadConfiguration';
import useTab from '../useTab';

import WebSSOForm from './Form';
import { WebSSOConfiguration } from './models';
import { labelDefineWebSSOConfiguration } from './translatedLabels';

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

  const isWebSSOConfigurationEmpty = isNil(initialConfiguration);

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
