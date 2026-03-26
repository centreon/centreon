import { useAtom, useSetAtom } from 'jotai';
import { useEffect, useState } from 'react';

import {
  isRevokingDialogCanceledAtom,
  tokensToDisableAtom,
  tokensToEnableAtom
} from '../../../atoms';

interface Props {
  change: (e: React.BaseSyntheticEvent) => void;
  checked: boolean;
}

const useStatus = ({ row }): Props => {
  const setTokensToDisable = useSetAtom(tokensToDisableAtom);
  const setTokensToEnable = useSetAtom(tokensToEnableAtom);
  const [isRevokingDialogCanceled, setIsRevokingDialog] = useAtom(
    isRevokingDialogCanceledAtom
  );

  const isActivated = !row.isRevoked;

  const [checked, setChecked] = useState(isActivated);

  useEffect(() => {
    if (isActivated !== checked) {
      setChecked(isActivated);

      setIsRevokingDialog(false);
    }
  }, [isActivated, isRevokingDialogCanceled]);

  const change = (e: React.BaseSyntheticEvent): void => {
    const value = e.target.checked;
    setChecked(value);

    if (checked) {
      setTokensToDisable([row]);

      return;
    }

    setTokensToEnable([row]);
  };

  return {
    change,
    checked
  };
};

export default useStatus;
