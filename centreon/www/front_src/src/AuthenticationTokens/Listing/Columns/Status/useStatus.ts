import { useEffect, useState } from 'react';

import { useSetAtom } from 'jotai';
import { tokensToDisableAtom, tokensToEnableAtom } from '../../../atoms';

interface Props {
  change: (e: React.BaseSyntheticEvent) => void;
  checked: boolean;
}

const useStatus = ({ row }): Props => {
  const setTokensToDisable = useSetAtom(tokensToDisableAtom);
  const setTokensToEnable = useSetAtom(tokensToEnableAtom);

  const isActivated = !row.isRevoked;

  const [checked, setChecked] = useState(isActivated);

  useEffect(() => {
    if (isActivated !== checked) {
      setChecked(isActivated);
    }
  }, [isActivated]);

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
