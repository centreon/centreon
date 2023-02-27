import { useTranslation } from 'react-i18next';

import { useSnackbar, Form, useMutationQuery, Method } from '@centreon/ui';

import useValidationSchema from '../useValidationSchema';
import {
  labelFailedToSaveWebSSOConfiguration,
  labelWebSSOConfigurationSaved
} from '../translatedLabels';
import { WebSSOConfiguration, WebSSOConfigurationToAPI } from '../models';
import { groups } from '../../groups';
import { Provider } from '../../models';
import { adaptWebSSOConfigurationToAPI } from '../../api/adapters';
import FormButtons from '../../FormButtons';
import { authenticationProvidersEndpoint } from '../../api/endpoints';

import { inputs } from './inputs';

interface Props {
  initialValues: WebSSOConfiguration;
  isLoading: boolean;
  loadWebSSOonfiguration: () => void;
}

const WebSSOForm = ({
  initialValues,
  loadWebSSOonfiguration,
  isLoading
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const { mutateAsync } = useMutationQuery<WebSSOConfigurationToAPI>({
    defaultFailureMessage: t(labelFailedToSaveWebSSOConfiguration),
    getEndpoint: () => authenticationProvidersEndpoint(Provider.WebSSO),
    method: Method.PUT
  });

  const { showSuccessMessage } = useSnackbar();

  const validationSchema = useValidationSchema();

  const submit = (
    values: WebSSOConfiguration,
    { setSubmitting }
  ): Promise<void> => {
    return mutateAsync(adaptWebSSOConfigurationToAPI(values))
      .then(() => {
        loadWebSSOonfiguration();
        showSuccessMessage(t(labelWebSSOConfigurationSaved));
      })
      .finally(() => setSubmitting(false));
  };

  return (
    <Form<WebSSOConfiguration>
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

export default WebSSOForm;
