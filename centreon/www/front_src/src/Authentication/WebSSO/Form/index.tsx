import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { Form, Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import FormButtons from '../../FormButtons';
import { adaptWebSSOConfigurationToAPI } from '../../api/adapters';
import { authenticationProvidersEndpoint } from '../../api/endpoints';
import { groups } from '../../groups';
import { Provider } from '../../models';
import { WebSSOConfiguration, WebSSOConfigurationToAPI } from '../models';
import {
  labelFailedToSaveWebSSOConfiguration,
  labelWebSSOConfigurationSaved
} from '../translatedLabels';
import useValidationSchema from '../useValidationSchema';

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

  const { mutateAsync } = useMutationQuery<WebSSOConfigurationToAPI, undefined>(
    {
      defaultFailureMessage: t(labelFailedToSaveWebSSOConfiguration),
      getEndpoint: () => authenticationProvidersEndpoint(Provider.WebSSO),
      method: Method.PUT
    }
  );

  const queryClient = useQueryClient();

  const { showSuccessMessage } = useSnackbar();

  const validationSchema = useValidationSchema();

  const submit = (
    values: WebSSOConfiguration,
    { setSubmitting }
  ): Promise<void> => {
    return mutateAsync({ payload: adaptWebSSOConfigurationToAPI(values) })
      .then(() => {
        queryClient.invalidateQueries({ queryKey: [Provider.WebSSO] });
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
