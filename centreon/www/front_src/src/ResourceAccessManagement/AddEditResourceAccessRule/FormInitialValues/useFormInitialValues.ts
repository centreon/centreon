import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { useFetchQuery } from '@centreon/ui';

import { editedResourceAccessRuleIdAtom, modalStateAtom } from '../../atom';

interface UseFormState {
  initialValues: object;
  isLoading: object;
}

const useFormInitialValues = (): UseFormState => {
  const { t } = useTranslation();
  const modalState = useAtomValue(modalStateAtom);
  const editedRuleId = useAtomValue(editedResourceAccessRuleIdAtom);
};

export default useFormInitialValues;
