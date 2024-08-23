import { isNil, not } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { LinearProgress } from '@mui/material';

import FormTitle from '../FormTitle';
import { SAMLConfigurationDecoder } from '../api/decoders';
import { Provider } from '../models';
import useLoadConfiguration from '../shared/useLoadConfiguration';
import useTab from '../useTab';

import Form from './Form';
import { SAMLConfiguration } from './models';
import { labelDefineSAMLConfiguration } from './translatedLabels';

const useStyles = makeStyles()((theme) => ({
  loading: {
    height: theme.spacing(0.5)
  }
}));

const SAMLConfigurationForm = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { sendingGetConfiguration, initialConfiguration, loadConfiguration } =
    useLoadConfiguration({
      decoder: SAMLConfigurationDecoder,
      providerType: Provider.SAML
    });

  const isConfigurationEmpty = isNil(initialConfiguration);

  useTab(isConfigurationEmpty);

  return (
    <div>
      <FormTitle title={t(labelDefineSAMLConfiguration)} />
      <div className={classes.loading}>
        {not(isConfigurationEmpty) && sendingGetConfiguration && (
          <LinearProgress />
        )}
      </div>
      <Form
        initialValues={initialConfiguration as SAMLConfiguration}
        isLoading={isConfigurationEmpty}
        loadConfiguration={loadConfiguration}
      />
    </div>
  );
};

export default SAMLConfigurationForm;
