import { and } from 'ramda';
import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import {
  Method,
  ResponseError,
  useFetchQuery,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { notificationdecoder } from '../../EditPanel/api/decoders';
import { notificationEndpoint } from '../../EditPanel/api/endpoints';
import { NotificationType } from '../../EditPanel/models';
import { adaptNotification as adaptFormFields } from '../../EditPanel/api/adapters';

import { addNotificationEndpoint } from './endpoints';
import { adaptNotification } from './adapters';

interface useDuplicateRequestState {
  submit: (
    values,
    {
      setSubmitting,
      resetForm
    }: {
      resetForm;
      setSubmitting;
    }
  ) => Promise<void>;
}

const useDuplicateRequest = ({
  onSettled,
  notificationId,
  payload: panelPayload,
  labelSuccess,
  labelFailed
}): useDuplicateRequestState => {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { showSuccessMessage } = useSnackbar();

  const { data } = useFetchQuery({
    decoder: notificationdecoder,
    getEndpoint: () => notificationEndpoint({ id: notificationId }),
    getQueryKey: () => ['duplicateNotification', notificationId],
    queryOptions: {
      enabled: and(!!notificationId, !panelPayload),
      suspense: false
    }
  });

  const { mutateAsync } = useMutationQuery({
    defaultFailureMessage: labelFailed,
    getEndpoint: (): string => addNotificationEndpoint,
    method: Method.POST
  });

  const submit = (values, { setSubmitting, resetForm }): Promise<void> => {
    const payload = panelPayload
      ? {
          ...adaptFormFields(panelPayload),
          name: values?.name
        }
      : {
          ...adaptNotification(data as NotificationType),
          name: values?.name
        };

    return mutateAsync(payload)
      .then((response) => {
        const { isError, message } = response as ResponseError;

        if (isError) {
          return;
        }

        showSuccessMessage(message || t(labelSuccess));
        queryClient.invalidateQueries(['notifications']);
        resetForm();
      })
      .finally(() => {
        onSettled();
        setSubmitting(false);
      });
  };

  return {
    submit
  };
};

export default useDuplicateRequest;
