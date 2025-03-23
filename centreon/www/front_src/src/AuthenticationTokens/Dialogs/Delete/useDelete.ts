import { useMemo } from 'react';

import { ResponseError, useSnackbar } from '@centreon/ui';
import { useAtom } from 'jotai';
import { isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useDelete as useDeleteRequest } from '../../api';

import { tokensToDeleteAtom } from '../../atoms';
import { labelTokenDeleted } from '../../translatedLabels';

interface UseDeleteState {
  confirm: () => void;
  close: () => void;
  isMutating: boolean;
  isOpened: boolean;
  name: string;
}

const useDelete = (): UseDeleteState => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const [tokensToDelete, setTokensToDelete] = useAtom(tokensToDeleteAtom);

  const name = tokensToDelete[0]?.name;
  const userId = tokensToDelete[0]?.user.id;

  const isOpened = useMemo(() => !isEmpty(tokensToDelete), [tokensToDelete]);
  const resetSelections = (): void => setTokensToDelete([]);

  const { deleteMutation, isMutating } = useDeleteRequest();

  const confirm = (): void => {
    deleteMutation({ userId, name }).then((response) => {
      const { isError } = response as ResponseError;

      if (isError) {
        return;
      }

      resetSelections();

      showSuccessMessage(t(labelTokenDeleted));
    });
  };

  return {
    confirm,
    close: resetSelections,
    isMutating: isMutating,
    isOpened,
    name
  };
};

export default useDelete;
