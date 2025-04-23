import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { Form, Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import FormButtons from '../../FormButtons';
import { adaptSAMLConfigurationToAPI } from '../../api/adapters';
import { authenticationProvidersEndpoint } from '../../api/endpoints';
import { groups } from '../../groups';
import { Provider } from '../../models';
import { SAMLConfiguration } from '../models';
import {
  labelFailedToSaveSAMLConfiguration,
  labelSAMLConfigurationSaved
} from '../translatedLabels';
import useValidationSchema from '../useValidationSchema';

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
  const queryClient = useQueryClient();

  const { showSuccessMessage } = useSnackbar();

  const validationSchema = useValidationSchema();

  const submit = (
    formikValues: SAMLConfiguration,
    { setSubmitting }
  ): Promise<void> =>
    mutateAsync({
      payload: adaptSAMLConfigurationToAPI(formikValues)
    })
      .then(() => {
        queryClient.invalidateQueries({ queryKey: [Provider.SAML] });
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
