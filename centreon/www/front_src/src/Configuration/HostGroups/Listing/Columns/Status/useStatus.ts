import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { ResponseError, useSnackbar } from '@centreon/ui';
import {
  labelHostGroupDisabled,
  labelHostGroupEnabled
} from '../../../translatedLabels';
import { useDisable, useEnable } from '../../api';

interface Props {
  change: (e: React.BaseSyntheticEvent) => void;
  isMutating: boolean;
  checked: boolean;
}

const useStatus = ({ row }): Props => {
  const { t } = useTranslation();

  const { showSuccessMessage } = useSnackbar();

  const [checked, setChecked] = useState(row?.isActivated);

  const { enableMutation, isMutating: isEnableMutating } = useEnable();
  const { disableMutation, isMutating: isDisableMutating } = useDisable();

  const change = (e: React.BaseSyntheticEvent): void => {
    const value = e.target.checked;
    setChecked(value);

    if (checked) {
      disableMutation({ ids: [row.id] }).then((response) => {
        const { isError } = response as ResponseError;
        if (isError) {
          setChecked(checked);

          return;
        }

        showSuccessMessage(t(labelHostGroupDisabled));
      });

      return;
    }

    enableMutation({ ids: [row.id] }).then((response) => {
      const { isError } = response as ResponseError;
      if (isError) {
        setChecked(checked);

        return;
      }

      showSuccessMessage(t(labelHostGroupEnabled));
    });
  };

  return {
    change,
    isMutating: isDisableMutating || isEnableMutating,
    checked
  };
};

export default useStatus;
