import { and } from 'ramda';
import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';

import {
  Method,
  ResponseError,
  useFetchQuery,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import {
  notificationdecoder,
  adaptNotification as adaptFormFields
} from '../../Panel/api';
import { notificationEndpoint } from '../../Panel/api/endpoints';
import { NotificationType } from '../../Panel/models';
import { htmlEmailyBodyAtom } from '../../Panel/atom';

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

  const htmlEmailyBody = useAtomValue(htmlEmailyBodyAtom);

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
      ? adaptFormFields({
          ...panelPayload,
          messages: { ...values.messages, formattedMessage: htmlEmailyBody },
          name: values?.name
        })
      : adaptNotification({
          ...data,
          name: values?.name
        } as NotificationType);

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
