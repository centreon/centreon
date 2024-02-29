import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import {
  Method,
  ResponseError,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import {
  labelFailure,
  labelResourceAccessRuleDeletedSuccess
} from '../../translatedLabels';

import { deleteSingleResourceAccessRuleEndpoint } from './endpoints';

interface UseDeleteRequestState {
  isLoading: boolean;
  submit: () => void;
}

const useDeleteRequest = ({ deleteRule, onSettled }): UseDeleteRequestState => {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { showSuccessMessage } = useSnackbar();

  const endpoint = deleteSingleResourceAccessRuleEndpoint(deleteRule.id);

  const { isMutating, mutateAsync } = useMutationQuery({
    defaultFailureMessage: t(labelFailure) as string,
    getEndpoint: (): string => endpoint,
    method: Method.DELETE
  });

  const submit = (): void => {
    mutateAsync({})
      .then((response) => {
        const { isError } = response as ResponseError;
        if (isError) {
          return;
        }

        showSuccessMessage(t(labelResourceAccessRuleDeletedSuccess));
        queryClient.invalidateQueries({ queryKey: ['resource-access-rules'] });
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
