import { useMemo } from 'react';

import { ResponseError, useSnackbar } from '@centreon/ui';
import { useAtom } from 'jotai';
import { isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useEnable as useEnableRequest } from '../../api';

import { tokensToEnableAtom } from '../../atoms';
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

  const name = tokensToEnable[0]?.name;
  const userId = tokensToEnable[0]?.user?.id || tokensToEnable[0]?.creator?.id;

  const isOpened = useMemo(() => !isEmpty(tokensToEnable), [tokensToEnable]);
  const resetSelections = (): void => setTokensToEnable([]);

  const { enableMutation, isMutating } = useEnableRequest();

  const confirm = (): void => {
    enableMutation({ userId, name }).then((response) => {
      const { isError } = response as ResponseError;

      if (isError) {
        return;
      }

      resetSelections();

      showSuccessMessage(t(labelTokenEnabled));
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

export default useEnable;
