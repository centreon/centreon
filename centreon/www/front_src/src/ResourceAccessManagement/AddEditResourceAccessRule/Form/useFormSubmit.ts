import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { equals } from 'ramda';
import { useAtom, useAtomValue } from 'jotai';

import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import { editedResourceAccessRuleIdAtom, modalStateAtom } from '../../atom';
import { ModalMode, ResourceAccessRule } from '../../models';
import { resourceAccessRuleEndpoint } from '../api/endpoints';
import {
  labelResourceAccessRuleAddedSuccess,
  labelResourceAccessRuleEditedSuccess
} from '../../translatedLabels';
import { adaptResourceAccessRule } from '../api/adapters';

interface UseFormState {
  submit: (
    values: Omit<ResourceAccessRule, 'id'>,
    { setSubmitting }
  ) => Promise<object>;
}

const useFormSubmit = (): UseFormState => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const queryClient = useQueryClient();

  const [modalState, setModalState] = useAtom(modalStateAtom);
  const editedRuleId = useAtomValue(editedResourceAccessRuleIdAtom);

  const labelMessage = equals(modalState.mode, ModalMode.Create)
    ? labelResourceAccessRuleAddedSuccess
    : labelResourceAccessRuleEditedSuccess;

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () =>
      equals(modalState.mode, ModalMode.Create)
        ? resourceAccessRuleEndpoint({})
        : resourceAccessRuleEndpoint({ id: editedRuleId }),
    method: equals(modalState.mode, ModalMode.Create)
      ? Method.POST
      : Method.PUT,
    onSettled: () => {
      setModalState({ isOpen: false, mode: modalState.mode });
      queryClient.invalidateQueries({ queryKey: ['resource-access-rules'] });
    },
    onSuccess: () => showSuccessMessage(t(labelMessage))
  });

  const submit = (
    values: Omit<ResourceAccessRule, 'id'>,
    { setSubmitting }
  ): Promise<object> => {
    const payload = adaptResourceAccessRule({ ...values });

    return mutateAsync({
      payload
    }).finally(() => setSubmitting(false));
  };

  return { submit };
};

export default useFormSubmit;
