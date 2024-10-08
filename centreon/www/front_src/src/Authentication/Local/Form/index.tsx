import { useTranslation } from 'react-i18next';

import { Form, useRequest, useSnackbar } from '@centreon/ui';
import type { Group } from '@centreon/ui';

import FormButtons from '../../FormButtons';
import { putPasswordPasswordSecurityPolicy } from '../../api';
import { PasswordSecurityPolicy } from '../models';
import {
  labelFailedToSavePasswordPasswordSecurityPolicy,
  labelPasswordBlockingPolicy,
  labelPasswordCasePolicy,
  labelPasswordExpirationPolicy,
  labelPasswordPasswordSecurityPolicySaved
} from '../translatedLabels';
import useValidationSchema from '../useValidationSchema';

import inputs from './inputs';

interface Props {
  initialValues: PasswordSecurityPolicy;
  isLoading: boolean;
  loadPasswordSecurityPolicy: () => void;
}

const groups: Array<Group> = [
  {
    name: labelPasswordCasePolicy,
    order: 1
  },
  {
    name: labelPasswordExpirationPolicy,
    order: 2
  },
  {
    name: labelPasswordBlockingPolicy,
    order: 3
  }
];

const PasswordSecurityPolicyForm = ({
  initialValues,
  isLoading,
  loadPasswordSecurityPolicy
}: Props): JSX.Element => {
  const validationSchema = useValidationSchema();
  const { showSuccessMessage } = useSnackbar();
  const { t } = useTranslation();

  const { sendRequest } = useRequest({
    defaultFailureMessage: t(labelFailedToSavePasswordPasswordSecurityPolicy),
    request: putPasswordPasswordSecurityPolicy
  });

  const submit = (
    values: PasswordSecurityPolicy,
    { setSubmitting }
  ): Promise<void> =>
    sendRequest(values)
      .then(() => {
        loadPasswordSecurityPolicy();
        showSuccessMessage(t(labelPasswordPasswordSecurityPolicySaved));
      })
      .finally(() => setSubmitting(false));

  return (
    <Form<PasswordSecurityPolicy>
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

export default PasswordSecurityPolicyForm;
