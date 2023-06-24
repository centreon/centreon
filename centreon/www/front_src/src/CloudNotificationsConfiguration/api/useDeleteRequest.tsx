import {
  includes,
  isEmpty,
  last,
  length,
  propEq,
  split,
  complement
} from 'ramda';
import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import {
  Method,
  ResponseError,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { labelFailedToDeleteNotifications } from '../translatedLabels';
import { notificationEndpoint } from '../EditPanel/api/endpoints';

interface useDeleteRequestState {
  isMutating: boolean;
  onConfirm: () => void;
}

const useDeleteRequest = ({
  fetchMethod = Method.DELETE,
  getEndpoint = () => notificationEndpoint({}),
  labelFailed = labelFailedToDeleteNotifications,
  labelSuccess,
  onSuccess,
  payload,
  selectedRows,
  setDialogOpen
}): useDeleteRequestState => {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { showSuccessMessage, showErrorMessage, showWarningMessage } =
    useSnackbar();

  const { isMutating, mutateAsync } = useMutationQuery({
    defaultFailureMessage: t(labelFailed) as string,
    getEndpoint,
    method: fetchMethod
  });

  const onConfirm = (): void => {
    mutateAsync(payload || {}).then((response) => {
      const { isError, statusCode, message, data } = response as ResponseError;

      if (isError) {
        return;
      }

      if (statusCode === 207) {
        const successfullResponses = data.filter(propEq('status', 204));
        const failedResponsesIds = data
          .filter(complement(propEq('status', 204)))
          .map((item) => item.href)
          .map((item) => parseInt(last(split('/', item)) as string, 10));

        if (isEmpty(successfullResponses)) {
          showErrorMessage(t(labelFailed));
          setDialogOpen?.(false);

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
          setDialogOpen?.(false);

          return;
        }
      }

      showSuccessMessage(message || t(labelSuccess));
      setDialogOpen?.(false);
      onSuccess?.();
      queryClient.invalidateQueries(['notifications']);
    });
  };

  return {
    isMutating,
    onConfirm
  };
};

export default useDeleteRequest;
