import { useEffect } from 'react';

import { useFormik } from 'formik';
import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { useRequest, useSnackbar } from '@centreon/ui';
import { acknowledgementAtom, userAtom } from '@centreon/ui-context';

import { Resource } from '../../models';
import {
  labelAcknowledgeCommandSent,
  labelAcknowledgedBy,
  labelRequired
} from '../../translatedLabels';
import { acknowledgeResources } from '../api';

import { boolean, object, string } from 'yup';
import DialogAcknowledge from './Dialog';

const validationSchema = object().shape({
  comment: string().required(labelRequired),
  force_active_checks: boolean(),
  is_sticky: boolean(),
  notify: boolean(),
  persistent: boolean()
});

interface Props {
  onClose: () => void;
  onSuccess: () => void;
  resources: Array<Resource>;
}

export interface AcknowledgeFormValues {
  acknowledgeAttachedResources: boolean;
  comment?: string;
  forceActiveChecks: boolean;
  isSticky: boolean;
  notify: boolean;
  persistent: boolean;
}

const AcknowledgeForm = ({
  resources,
  onClose,
  onSuccess
}: Props): JSX.Element | null => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const {
    sendRequest: sendAcknowledgeResources,
    sending: sendingAcknowledgeResources
  } = useRequest({
    request: acknowledgeResources
  });

  const { alias } = useAtomValue(userAtom);
  const acknowledgement = useAtomValue(acknowledgementAtom);

  const form = useFormik<AcknowledgeFormValues>({
    initialValues: {
      acknowledgeAttachedResources: acknowledgement.with_services,
      comment: undefined,
      forceActiveChecks: acknowledgement.force_active_checks,
      isSticky: acknowledgement.sticky,
      notify: acknowledgement.notify,
      persistent: acknowledgement.persistent
    },
    onSubmit: (values): void => {
      sendAcknowledgeResources({
        params: values,
        resources
      }).then(() => {
        showSuccessMessage(t(labelAcknowledgeCommandSent));
        onSuccess();
      });
    },
    validationSchema
  });

  useEffect(() => {
    form.setFieldValue('comment', `${t(labelAcknowledgedBy)} ${alias}`);
  }, []);

  return (
    <DialogAcknowledge
      canConfirm={form.isValid}
      errors={form.errors}
      handleChange={form.handleChange}
      resources={resources}
      submitting={sendingAcknowledgeResources}
      values={form.values}
      onCancel={onClose}
      onConfirm={form.submitForm}
    />
  );
};

export default AcknowledgeForm;
