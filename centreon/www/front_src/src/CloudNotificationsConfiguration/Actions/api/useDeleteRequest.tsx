import { useQueryClient } from '@tanstack/react-query';
import {
  complement,
  equals,
  includes,
  isEmpty,
  isNil,
  last,
  length,
  prop,
  propEq,
  split
} from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  Method,
  ResponseError,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { DeleteType } from '../../models';
import {
  labelFailedToDeleteNotification,
  labelFailedToDeleteNotifications,
  labelFailedToDeleteSelectedNotifications,
  labelNotificationSuccessfullyDeleted,
  labelNotificationsSuccessfullyDeleted
} from '../../translatedLabels';

import {
  deleteMultipleNotificationEndpoint,
  deleteSingleNotificationEndpoint
} from './endpoints';

interface UseDeleteRequestState {
  isLoading: boolean;
  submit: () => void;
}

const useDeleteRequest = ({
  onSettled,
  selectedRows,
  deleteNotification
}): UseDeleteRequestState => {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { showSuccessMessage, showErrorMessage, showWarningMessage } =
    useSnackbar();

  const isSingleItem = equals(deleteNotification.type, DeleteType.SingleItem);

  const fetchMethod = isSingleItem ? Method.DELETE : Method.POST;
  const endpoint = isSingleItem
    ? deleteSingleNotificationEndpoint(deleteNotification.id)
    : deleteMultipleNotificationEndpoint;
  const labelFailed = isSingleItem
    ? labelFailedToDeleteNotification
    : labelFailedToDeleteSelectedNotifications;
  const labelSuccess = isSingleItem
    ? labelNotificationSuccessfullyDeleted
    : labelNotificationsSuccessfullyDeleted;
  const payload = isSingleItem ? {} : { ids: deleteNotification.id };

  const { isMutating, mutateAsync } = useMutationQuery({
    defaultFailureMessage: t(labelFailed) as string,
    getEndpoint: (): string => endpoint,
    method: fetchMethod
  });

  const submit = (): void => {
    mutateAsync({
      payload: payload || {}
    })
      .then((response) => {
        const { isError, message, data } = response as ResponseError;

        if (isError) {
          return;
        }

        const successfullResponses =
          data?.filter(propEq(204, 'status')) || isNil(data);
        const failedResponsesIds = data
          ?.filter(complement(propEq(204, 'status')))
          .map(prop('href'))
          .map((item) => Number.parseInt(last(split('/', item)) as string, 10));

        if (isEmpty(successfullResponses)) {
          showErrorMessage(t(labelFailed));

          return;
        }

        if (length(successfullResponses) < length(data)) {
          const failedResponsesName = selectedRows
            ?.filter((item) => includes(item.id, failedResponsesIds))
            .map((item) => item.name);

          showWarningMessage(
            `${labelFailedToDeleteNotifications}: ${failedResponsesName.join(
              ', '
            )}`
          );

          return;
        }

        showSuccessMessage(message || t(labelSuccess));
        queryClient.invalidateQueries({ queryKey: ['notifications'] });
      })
      .finally(() => {
        onSettled();
      });
  };

  return {
    isLoading: isMutating,
    submit
  };
};

export default useDeleteRequest;
