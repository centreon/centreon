import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { useAtom } from 'jotai';

import {
  Method,
  ResponseError,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { labelResourceAccessRuleAddedSuccess } from '../../translatedLabels';
import { modalStateAtom } from '../atom';

import { resourceAccessRuleEndpoint } from './api/endpoints';

type UseFormSubmitType = {
  submit: (values, { setSubmitting }) => Promise<void>;
};

const useFormSubmit = (): UseFormSubmitType => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const queryClient = useQueryClient();
  const [modalState, setModalState] = useAtom(modalStateAtom);

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () => resourceAccessRuleEndpoint({}),
    method: Method.POST
  });

  const submit = (values, { setSubmitting }): Promise<void> => {
    const labelMessage = labelResourceAccessRuleAddedSuccess;
    const payload = values;

    return mutateAsync(payload)
      .then((response) => {
        const { isError } = response as ResponseError;
        if (isError) {
          return;
        }
        showSuccessMessage(t(labelMessage));
        setModalState({ ...modalState, isOpen: false });

        queryClient.invalidateQueries({ queryKey: ['resource-access-rules'] });
      })
      .finally(() => setSubmitting(false));
  };

  return { submit };
};

export default useFormSubmit;
