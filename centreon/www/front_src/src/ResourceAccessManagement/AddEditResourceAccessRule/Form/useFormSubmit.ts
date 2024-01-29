import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { equals } from 'ramda';
import { useAtomValue, useSetAtom } from 'jotai';

import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import {
  editedResourceAccessRuleIdAtom,
  modalStateAtom,
  resourceAccessRuleModalModeAtom
} from '../../atom';
import { ModalMode, ResourceAccessRule } from '../../models';
import { resourceAccessRuleEndpoint } from '../api/endpoints';
import {
  labelResourceAccessRuleAddedSuccess,
  labelResourceAccessRuleEditedSuccess
} from '../../translatedLabels';
import { adaptResourceAccessRule } from '../api/adapters';

interface UseFormState {
  submit: (values: ResourceAccessRule, { setSubmitting }) => Promise<object>;
}

const useFormSubmit = (): UseFormState => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const queryClient = useQueryClient();

  const modalMode = useAtomValue(resourceAccessRuleModalModeAtom);
  const editedRuleId = useAtomValue(editedResourceAccessRuleIdAtom);
  const setModalState = useSetAtom(modalStateAtom);

  const labelMessage = equals(modalMode, ModalMode.Create)
    ? t(labelResourceAccessRuleAddedSuccess)
    : t(labelResourceAccessRuleEditedSuccess);

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () =>
      equals(modalMode, ModalMode.Create)
        ? resourceAccessRuleEndpoint({})
        : resourceAccessRuleEndpoint({ id: editedRuleId }),
    method: equals(modalMode, ModalMode.Create) ? Method.POST : Method.PUT,
    onSettled: () => {
      setModalState({ isOpen: false, mode: ModalMode.Create });
      queryClient.invalidateQueries({ queryKey: ['resource-access-rules'] });
    },
    onSuccess: () => showSuccessMessage(t(labelMessage))
  });

  const submit = (
    values: ResourceAccessRule,
    { setSubmitting }
  ): Promise<object> => {
    const payload = adaptResourceAccessRule({ ...values });

    return mutateAsync(payload).finally(() => setSubmitting(false));
  };

  return { submit };
};

export default useFormSubmit;
