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

import {
  DeleteResourceAccessRuleType,
  DeleteType,
  ResourceAccessRuleType
} from '../../models';
import {
  labelFailedToDeleteRule,
  labelFailedToDeleteSelectedRules,
  labelResourceAccessRuleDeletedSuccess,
  labelResourceAccessRulesDeletedSuccess
} from '../../translatedLabels';

import {
  deleteMultipleRulesEndpoint,
  deleteSingleResourceAccessRuleEndpoint
} from './endpoints';

interface UseDeleteRequestProps {
  deleteRule: DeleteResourceAccessRuleType;
  onSettled: () => void;
  selectedRows: Array<ResourceAccessRuleType>;
}

interface UseDeleteRequestState {
  isLoading: boolean;
  submit: () => void;
}

const useDeleteRequest = ({
  deleteRule,
  onSettled,
  selectedRows
}: UseDeleteRequestProps): UseDeleteRequestState => {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { showSuccessMessage, showErrorMessage, showWarningMessage } =
    useSnackbar();

  const isSingleDelete = equals(deleteRule.deleteType, DeleteType.SingleItem);
  const fetchMethod = isSingleDelete ? Method.DELETE : Method.POST;
  const endpoint = isSingleDelete
    ? deleteSingleResourceAccessRuleEndpoint(deleteRule.id as number)
    : deleteMultipleRulesEndpoint;
  const labelFailed = isSingleDelete
    ? labelFailedToDeleteRule
    : labelFailedToDeleteSelectedRules;
  const labelSuccess = isSingleDelete
    ? labelResourceAccessRuleDeletedSuccess
    : labelResourceAccessRulesDeletedSuccess;
  const payload = isSingleDelete ? {} : { ids: deleteRule.id as Array<number> };

  const { isMutating, mutateAsync } = useMutationQuery({
    defaultFailureMessage: t(labelFailed) as string,
    getEndpoint: (): string => endpoint,
    method: fetchMethod,
    onSettled,
    onSuccess: (response) => {
      const { data } = response as ResponseError;

      const successfullResponses =
        data?.filter(propEq(204, 'status')) || isNil(data);
      const failedResponses = data?.filter(complement(propEq(204, 'status')));
      const failedResponsesIds = failedResponses
        .map(prop('href'))
        .map((item: string) =>
          Number.parseInt(last(split('/', item)) as string, 10)
        );

      if (isEmpty(successfullResponses)) {
        showErrorMessage(t(labelFailed));

        return;
      }

      if (length(successfullResponses) < length(data)) {
        const failedResponsesNames = selectedRows
          ?.filter((item) => includes(item.id, failedResponsesIds))
          .map((item) => item.name);

        showWarningMessage(
          `${labelFailedToDeleteSelectedRules}: ${failedResponsesNames.join(
            ', '
          )}`
        );

        return;
      }

      showSuccessMessage(t(labelSuccess));
      queryClient.invalidateQueries({ queryKey: ['resource-access-rules'] });
    }
  });

  const submit = (): void => {
    mutateAsync({ payload });
  };

  return {
    isLoading: isMutating,
    submit
  };
};

export default useDeleteRequest;
