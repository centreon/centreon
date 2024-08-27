import { useQueryClient } from '@tanstack/react-query';
import { omit } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  Method,
  useFetchQuery,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { resourceAccessRuleDecoder } from '../../AddEditResourceAccessRule/api/decoders';
import { resourceAccessRuleEndpoint } from '../../AddEditResourceAccessRule/api/endpoints';
import { GetResourceAccessRule } from '../../models';

import { adaptRule } from './adapters';

interface UseDuplicateRequestProps {
  labelFailure: string;
  labelSuccess: string;
  onSettled: () => void;
  ruleId: number | null;
}
interface UseDuplicateRequestState {
  submit: (
    values,
    {
      resetForm,
      setSubmitting
    }: {
      resetForm;
      setSubmitting;
    }
  ) => Promise<object>;
}

const useDuplicateRequest = ({
  labelFailure,
  labelSuccess,
  onSettled,
  ruleId
}: UseDuplicateRequestProps): UseDuplicateRequestState => {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { showSuccessMessage } = useSnackbar();

  const { data } = useFetchQuery({
    decoder: resourceAccessRuleDecoder,
    getEndpoint: (): string => resourceAccessRuleEndpoint({ id: ruleId }),
    getQueryKey: () => ['duplicate-resource-access-rule', ruleId],
    queryOptions: {
      enabled: !!ruleId,
      suspense: false
    }
  });

  const { mutateAsync } = useMutationQuery({
    defaultFailureMessage: labelFailure,
    getEndpoint: (): string => resourceAccessRuleEndpoint({}),
    method: Method.POST,
    onSettled,
    onSuccess: () => {
      showSuccessMessage(t(labelSuccess));
      queryClient.invalidateQueries({ queryKey: ['resource-access-rules'] });
    }
  });

  const submit = (values, { resetForm, setSubmitting }): Promise<object> => {
    const payload = adaptRule({
      ...omit(['id'], data),
      name: values?.name
    } as GetResourceAccessRule);

    return mutateAsync({ payload }).finally(() => {
      resetForm();
      setSubmitting(false);
    });
  };

  return { submit };
};

export default useDuplicateRequest;
