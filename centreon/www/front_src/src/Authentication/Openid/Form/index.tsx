import { useQueryClient } from '@tanstack/react-query';
import { FormikValues } from 'formik';
import { all, isEmpty, isNil, not, or, pick, pipe, values } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Form, Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import FormButtons from '../../FormButtons';
import { adaptOpenidConfigurationToAPI } from '../../api/adapters';
import { authenticationProvidersEndpoint } from '../../api/endpoints';
import { groups } from '../../groups';
import { Provider } from '../../models';
import { OpenidConfiguration, OpenidConfigurationToAPI } from '../models';
import {
  labelFailedToSaveOpenidConfiguration,
  labelOpenIDConnectConfigurationSaved,
  labelRequired
} from '../translatedLabels';
import useValidationSchema from '../useValidationSchema';

import { inputs } from './inputs';

interface Props {
  initialValues: OpenidConfiguration;
  isLoading: boolean;
  loadOpenidConfiguration: () => void;
}

const isNilOrEmpty = (value): boolean => or(isNil(value), isEmpty(value));

const OpenidForm = ({
  initialValues,
  loadOpenidConfiguration,
  isLoading
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const { mutateAsync } = useMutationQuery<OpenidConfigurationToAPI, undefined>(
    {
      defaultFailureMessage: t(labelFailedToSaveOpenidConfiguration),
      getEndpoint: () => authenticationProvidersEndpoint(Provider.Openid),
      method: Method.PUT
    }
  );
  const queryClient = useQueryClient();

  const { showSuccessMessage } = useSnackbar();

  const validationSchema = useValidationSchema();

  const submit = (
    formikValues: OpenidConfiguration,
    { setSubmitting }
  ): Promise<void> =>
    mutateAsync({
      payload: adaptOpenidConfigurationToAPI(formikValues)
    })
      .then(() => {
        queryClient.invalidateQueries({ queryKey: [Provider.Openid] });
        loadOpenidConfiguration();
        showSuccessMessage(t(labelOpenIDConnectConfigurationSaved));
      })
      .finally(() => setSubmitting(false));

  const validate = (formikValues: FormikValues): object => {
    const isUserInfoOrIntrospectionTokenEmpty = pipe(
      pick(['introspectionTokenEndpoint', 'userinfoEndpoint']),
      values,
      all(isNilOrEmpty)
    )(formikValues);

    if (not(isUserInfoOrIntrospectionTokenEmpty)) {
      return {};
    }

    return {
      introspectionTokenEndpoint: t(labelRequired),
      userinfoEndpoint: t(labelRequired)
    };
  };

  return (
    <Form<OpenidConfiguration>
      isCollapsible
      Buttons={FormButtons}
      groups={groups}
      initialValues={initialValues}
      inputs={inputs}
      isLoading={isLoading}
      submit={submit}
      validate={validate}
      validationSchema={validationSchema}
    />
  );
};

export default OpenidForm;
