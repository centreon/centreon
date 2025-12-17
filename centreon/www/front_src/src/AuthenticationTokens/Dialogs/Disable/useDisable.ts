import { ResponseError, useSnackbar } from '@centreon/ui';

import { useAtom, useSetAtom } from 'jotai';
import { isEmpty } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { useDisable as useDisableRequest } from '../../api';
import { isRevokingDialogCanceledAtom, tokensToDisableAtom } from '../../atoms';
import { labelTokenDisabled } from '../../translatedLabels';

interface UseDeleteState {
  confirm: () => void;
  close: () => void;
  isMutating: boolean;
  isOpened: boolean;
  name: string;
}

const useDisable = (): UseDeleteState => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const [tokensToDisable, setTokensToDisable] = useAtom(tokensToDisableAtom);

  const setIsRevokingDialogCanceled = useSetAtom(isRevokingDialogCanceledAtom);

  const name = tokensToDisable[0]?.name;
  const userId =
    tokensToDisable[0]?.user?.id || tokensToDisable[0]?.creator?.id;

  const isOpened = useMemo(() => !isEmpty(tokensToDisable), [tokensToDisable]);
  const resetSelections = (): void => {
    setTokensToDisable([]);

    setIsRevokingDialogCanceled(true);
  };

  const { disableMutation, isMutating } = useDisableRequest();

  const confirm = (): void => {
    disableMutation({ userId, name }).then((response) => {
      const { isError } = response as ResponseError;

      if (isError) {
        return;
      }

      setTokensToDisable([]);

      showSuccessMessage(t(labelTokenDisabled));
    });
  };

  return {
    confirm,
    close: resetSelections,
    isMutating,
    isOpened,
    name
  };
};

export default useDisable;
