<<<<<<< HEAD
import { useEffect } from 'react';
=======
import * as React from 'react';
>>>>>>> centreon/dev-21.10.x

import { useFormik } from 'formik';
import * as Yup from 'yup';
import { useTranslation } from 'react-i18next';
<<<<<<< HEAD
import { useAtomValue } from 'jotai/utils';

import { useSnackbar, useRequest } from '@centreon/ui';
import { acknowledgementAtom, userAtom } from '@centreon/ui-context';
=======

import { useSnackbar, useRequest } from '@centreon/ui';
import { useUserContext } from '@centreon/ui-context';
>>>>>>> centreon/dev-21.10.x

import {
  labelRequired,
  labelAcknowledgeCommandSent,
  labelAcknowledgedBy,
} from '../../../translatedLabels';
import { Resource } from '../../../models';
import { acknowledgeResources } from '../../api';

import DialogAcknowledge from './Dialog';

const validationSchema = Yup.object().shape({
  comment: Yup.string().required(labelRequired),
  force_active_checks: Yup.boolean(),
  is_sticky: Yup.boolean(),
  notify: Yup.boolean(),
  persistent: Yup.boolean(),
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
  onSuccess,
}: Props): JSX.Element | null => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const {
    sendRequest: sendAcknowledgeResources,
    sending: sendingAcknowledgeResources,
  } = useRequest({
    request: acknowledgeResources,
  });

<<<<<<< HEAD
  const { alias } = useAtomValue(userAtom);
  const acknowledgement = useAtomValue(acknowledgementAtom);
=======
  const { alias, acknowledgement } = useUserContext();
>>>>>>> centreon/dev-21.10.x

  const form = useFormik<AcknowledgeFormValues>({
    initialValues: {
      acknowledgeAttachedResources: acknowledgement.with_services,
      comment: undefined,
      forceActiveChecks: acknowledgement.force_active_checks,
      isSticky: acknowledgement.sticky,
      notify: acknowledgement.notify,
      persistent: acknowledgement.persistent,
    },
    onSubmit: (values): void => {
      sendAcknowledgeResources({
        params: values,
        resources,
      }).then(() => {
        showSuccessMessage(t(labelAcknowledgeCommandSent));
        onSuccess();
      });
    },
    validationSchema,
  });

<<<<<<< HEAD
  useEffect(() => {
=======
  React.useEffect(() => {
>>>>>>> centreon/dev-21.10.x
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
