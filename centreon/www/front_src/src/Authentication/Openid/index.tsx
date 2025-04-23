import { isNil, not } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { LinearProgress } from '@mui/material';

import FormTitle from '../FormTitle';
import { openidConfigurationDecoder } from '../api/decoders';
import { Provider } from '../models';
import useLoadConfiguration from '../shared/useLoadConfiguration';
import useTab from '../useTab';

import Form from './Form';
import { OpenidConfiguration } from './models';
import { labelDefineOpenIDConnectConfiguration } from './translatedLabels';

const useStyles = makeStyles()((theme) => ({
  loading: {
    height: theme.spacing(0.5)
  }
}));

const OpenidConfigurationForm = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { loadConfiguration, initialConfiguration, sendingGetConfiguration } =
    useLoadConfiguration<OpenidConfiguration>({
      decoder: openidConfigurationDecoder,
      providerType: Provider.Openid
    });

  const isOpenidConfigurationEmpty = isNil(initialConfiguration);

  useTab(isOpenidConfigurationEmpty);

  return (
    <div>
      <FormTitle title={t(labelDefineOpenIDConnectConfiguration)} />
      <div className={classes.loading}>
        {not(isOpenidConfigurationEmpty) && sendingGetConfiguration && (
          <LinearProgress />
        )}
      </div>
      <Form
        initialValues={initialConfiguration as OpenidConfiguration}
        isLoading={isOpenidConfigurationEmpty}
        loadOpenidConfiguration={loadConfiguration}
      />
    </div>
  );
};

export default OpenidConfigurationForm;
