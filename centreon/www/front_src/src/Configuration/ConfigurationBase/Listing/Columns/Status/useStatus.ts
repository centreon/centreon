import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { ResponseError, useSnackbar } from '@centreon/ui';
import { useDisable, useEnable } from '../../../api';
import {
  labelResourceDisabled,
  labelResourceEnabled
} from '../../../translatedLabels';

import { useAtomValue } from 'jotai';
import { configurationAtom } from '../../../../atoms';

import { capitalize } from '@mui/material';

interface Props {
  change: (e: React.BaseSyntheticEvent) => void;
  isMutating: boolean;
  checked: boolean;
}

const useStatus = ({ row }): Props => {
  const { t } = useTranslation();

  const { showSuccessMessage } = useSnackbar();

  const configuration = useAtomValue(configurationAtom);
  const resourceType = configuration?.resourceType;

  const isActivated = row.isActivated;

  const [checked, setChecked] = useState(isActivated);

  const labelResourceType = capitalize(resourceType as string);

  useEffect(() => {
    if (isActivated !== checked) {
      setChecked(isActivated);
    }
  }, [isActivated]);

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

        showSuccessMessage(t(labelResourceDisabled(labelResourceType)));
      });

      return;
    }

    enableMutation({ ids: [row.id] }).then((response) => {
      const { isError } = response as ResponseError;
      if (isError) {
        setChecked(checked);

        return;
      }

      showSuccessMessage(t(labelResourceEnabled(labelResourceType)));
    });
  };

  return {
    change,
    isMutating: isDisableMutating || isEnableMutating,
    checked
  };
};

export default useStatus;
