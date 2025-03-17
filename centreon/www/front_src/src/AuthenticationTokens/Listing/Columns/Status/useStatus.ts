import { useEffect, useState } from 'react';
import { useEnable } from '../../../api';

import { useSetAtom } from 'jotai';
import { tokensToDisableAtom } from '../../../atoms';

interface Props {
  change: (e: React.BaseSyntheticEvent) => void;
  isMutating: boolean;
  checked: boolean;
}

const useStatus = ({ row }): Props => {
  const setTokensToDisable = useSetAtom(tokensToDisableAtom);

  const isActivated = !row.isRevoked;

  const [checked, setChecked] = useState(isActivated);

  useEffect(() => {
    if (isActivated !== checked) {
      setChecked(isActivated);
    }
  }, [isActivated]);

  const { enableMutation, isMutating } = useEnable();

  const change = (e: React.BaseSyntheticEvent): void => {
    const value = e.target.checked;
    setChecked(value);

    if (checked) {
      setTokensToDisable([row]);

      return;
    }

    enableMutation({ userId: row.user.id, name: row.name });
  };

  return {
    change,
    isMutating,
    checked
  };
};

export default useStatus;
