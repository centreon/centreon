import { equals } from 'ramda';
import { useAtomValue } from 'jotai';

import { useFetchQuery } from '@centreon/ui';

import { ModalMode, ResourceAccessRule } from '../../models';
import { editedResourceAccessRuleIdAtom, modalStateAtom } from '../../atom';
import { resourceAccessRuleDecoder } from '../api/decoders';
import { resourceAccessRuleEndpoint } from '../api/endpoints';

import { getEmptyInitialValues, getInitialValues } from './initialValues';

interface UseFormState {
  initialValues: Omit<ResourceAccessRule, 'id'>;
  isLoading: boolean;
}

const useFormInitialValues = (): UseFormState => {
  const modalState = useAtomValue(modalStateAtom);
  const editRuleId = useAtomValue(editedResourceAccessRuleIdAtom);

  const { data, isLoading: loading } = useFetchQuery({
    decoder: resourceAccessRuleDecoder,
    getEndpoint: () => resourceAccessRuleEndpoint({ id: editRuleId }),
    getQueryKey: () => ['resource-access-rules', editRuleId],
    queryOptions: {
      cacheTime: 0,
      enabled: equals(modalState.mode, ModalMode.Edit),
      suspense: false
    }
  });

  const initialValues =
    equals(modalState.mode, ModalMode.Edit) && data
      ? getInitialValues({ ...data })
      : getEmptyInitialValues();

  const isLoading = equals(modalState.mode, ModalMode.Edit) ? loading : false;

  return { initialValues, isLoading };
};

export default useFormInitialValues;
