import { useQueryClient } from '@tanstack/react-query';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Method, useBulkResponse, useMutationQuery } from '@centreon/ui';

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

  const handleBulkResponse = useBulkResponse();

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
      const data = response.results;

      handleBulkResponse({
        data,
        labelWarning: t(labelFailedToDeleteSelectedRules),
        labelFailed: t(labelFailed),
        labelSuccess: t(labelSuccess),
        items: selectedRows
      });

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
