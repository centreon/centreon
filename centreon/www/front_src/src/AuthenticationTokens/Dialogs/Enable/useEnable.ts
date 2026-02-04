import { ResponseError, useSnackbar } from '@centreon/ui';

import { useAtom, useSetAtom } from 'jotai';
import { isEmpty } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { useEnable as useEnableRequest } from '../../api';
import { isRevokingDialogCanceledAtom, tokensToEnableAtom } from '../../atoms';
import { labelTokenEnabled } from '../../translatedLabels';

interface UseDeleteState {
  confirm: () => void;
  close: () => void;
  isMutating: boolean;
  isOpened: boolean;
  name: string;
}

const useEnable = (): UseDeleteState => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const [tokensToEnable, setTokensToEnable] = useAtom(tokensToEnableAtom);
  const setIsRevokingDialogCanceled = useSetAtom(isRevokingDialogCanceledAtom);

  const name = tokensToEnable[0]?.name;
  const userId = tokensToEnable[0]?.user?.id || tokensToEnable[0]?.creator?.id;

  const isOpened = useMemo(() => !isEmpty(tokensToEnable), [tokensToEnable]);

  const resetSelections = (): void => {
    setTokensToEnable([]);

    setIsRevokingDialogCanceled(true);
  };

  const { enableMutation, isMutating } = useEnableRequest();

  const confirm = (): void => {
    enableMutation({ name, userId }).then((response) => {
      const { isError } = response as ResponseError;

      if (isError) {
        return;
      }

      setTokensToEnable([]);

      showSuccessMessage(t(labelTokenEnabled));
    });
  };

  return {
    close: resetSelections,
    confirm,
    isMutating,
    isOpened,
    name
  };
};

export default useEnable;
