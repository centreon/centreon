import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { equals } from 'ramda';
import { useAtomValue, useSetAtom } from 'jotai';

import {
  Method,
  ResponseError,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import {
  editedResourceAccessRuleIdAtom,
  modalStateAtom,
  resourceAccessRuleModalModeAtom
} from '../../atom';
import { ModalMode } from '../../models';
import { resourceAccessRuleEndpoint } from '../api/endpoints';
import {
  labelResourceAccessRuleAddedSuccess,
  labelResourceAccessRuleEditedSuccess
} from '../../translatedLabels';

interface UseFormState {
  submit: (values, { setSubmitting }) => Promise<void>;
}

const useFormSubmit = (): UseFormState => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const queryClient = useQueryClient();

  const modalMode = useAtomValue(resourceAccessRuleModalModeAtom);
  const editedRuleId = useAtomValue(editedResourceAccessRuleIdAtom);
  const setModalState = useSetAtom(modalStateAtom);

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () =>
      equals(modalMode, ModalMode.Create)
        ? resourceAccessRuleEndpoint({})
        : resourceAccessRuleEndpoint({ id: editedRuleId }),
    method: equals(modalMode, ModalMode.Create) ? Method.POST : Method.PUT
  });

  const submit = (values, { setSubmitting }): Promise<void> => {
    const labelMessage = equals(modalMode, ModalMode.Create)
      ? t(labelResourceAccessRuleAddedSuccess)
      : t(labelResourceAccessRuleEditedSuccess);

    const payload = values;

    return mutateAsync(payload)
      .then((response) => {
        const { isError } = response as ResponseError;
        if (isError) {
          return;
        }
        showSuccessMessage(t(labelMessage));
        setModalState({ isOpen: false, mode: ModalMode.Create });
        queryClient.invalidateQueries({ queryKey: ['resource_access_rules'] });
      })
      .finally(() => setSubmitting(false));
  };

  return { submit };
};

export default useFormSubmit;
