import { equals } from 'ramda';
import { useAtomValue } from 'jotai';
import { useIsFetching, useQueryClient } from '@tanstack/react-query';

import { ModalMode, ResourceAccessRule } from '../../models';
import { editedResourceAccessRuleIdAtom, modalStateAtom } from '../../atom';

import { getEmptyInitialValues, getInitialValues } from './initialValues';

interface UseFormState {
  initialValues: Omit<ResourceAccessRule, 'id'>;
  isLoading: boolean;
}

export const query = {
  useQueryClient
};

const useFormInitialValues = (): UseFormState => {
  const modalState = useAtomValue(modalStateAtom);
  const editRuleId = useAtomValue(editedResourceAccessRuleIdAtom);

  const data = query
    .useQueryClient()
    .getQueryData(['resource-access-rule', editRuleId]);

  const isFetching = useIsFetching({
    queryKey: ['resource-access-rule', editRuleId]
  });

  const initialValues =
    equals(modalState.mode, ModalMode.Edit) && data
      ? getInitialValues(data)
      : getEmptyInitialValues();

  const isLoading = equals(modalState.mode, ModalMode.Edit)
    ? !!isFetching
    : false;

  return { initialValues, isLoading };
};

export default useFormInitialValues;
