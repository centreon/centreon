import { useQueryClient } from '@tanstack/react-query';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  Method,
  ResponseError,
  useBulkResponse,
  useMutationQuery
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

  const handleBulkResponse = useBulkResponse();

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
        const { isError, results } = response as ResponseError;

        if (isError) {
          return;
        }

        handleBulkResponse({
          data: results,
          labelWarning: t(labelFailedToDeleteNotifications),
          labelFailed: t(labelFailed),
          labelSuccess: t(labelSuccess),
          items: selectedRows
        });

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
