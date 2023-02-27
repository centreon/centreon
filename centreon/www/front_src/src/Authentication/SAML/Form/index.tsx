import { useTranslation } from 'react-i18next';

import { useSnackbar, Form, useMutationQuery, Method } from '@centreon/ui';

import useValidationSchema from '../useValidationSchema';
import {
  labelFailedToSaveSAMLConfiguration,
  labelSAMLConfigurationSaved
} from '../translatedLabels';
import { SAMLConfiguration } from '../models';
import FormButtons from '../../FormButtons';
import { groups } from '../../groups';
import { Provider } from '../../models';
import { adaptSAMLConfigurationToAPI } from '../../api/adapters';
import { authenticationProvidersEndpoint } from '../../api/endpoints';

import { inputs } from './inputs';

interface Props {
  initialValues: SAMLConfiguration;
  isLoading: boolean;
  loadConfiguration: () => void;
}

const SAMLForm = ({
  initialValues,
  loadConfiguration,
  isLoading
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const { mutateAsync } = useMutationQuery({
    defaultFailureMessage: t(labelFailedToSaveSAMLConfiguration),
    getEndpoint: () => authenticationProvidersEndpoint(Provider.SAML),
    method: Method.PUT
  });

  const { showSuccessMessage } = useSnackbar();

  const validationSchema = useValidationSchema();

  const submit = (
    formikValues: SAMLConfiguration,
    { setSubmitting }
  ): Promise<void> =>
    mutateAsync(adaptSAMLConfigurationToAPI(formikValues))
      .then(() => {
        loadConfiguration();
        showSuccessMessage(t(labelSAMLConfigurationSaved));
      })
      .finally(() => setSubmitting(false));

  return (
    <Form<SAMLConfiguration>
      isCollapsible
      Buttons={FormButtons}
      groups={groups}
      initialValues={initialValues}
      inputs={inputs}
      isLoading={isLoading}
      submit={submit}
      validationSchema={validationSchema}
    />
  );
};

export default SAMLForm;
