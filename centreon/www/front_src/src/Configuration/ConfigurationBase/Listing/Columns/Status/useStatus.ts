import { useAtomValue } from 'jotai';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { ResponseError, useSnackbar } from '@centreon/ui';
import { capitalize } from '@mui/material';
import { useDisable, useEnable } from '../../../api';

import { configurationAtom } from '../../../../atoms';

import {
  labelResourceDisabled,
  labelResourceEnabled
} from '../../../translatedLabels';

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

  const labelSuccessMessage = checked
    ? t(labelResourceDisabled(labelResourceType))
    : t(labelResourceEnabled(labelResourceType));

  const handleApiResponse = (response) => {
    const { isError } = response as ResponseError;
    if (isError) {
      setChecked(checked);

      return;
    }

    showSuccessMessage(labelSuccessMessage);
  };

  const payload = useMemo(() => ({ ids: [row.id] }), [row]);

  const change = (e: React.BaseSyntheticEvent): void => {
    const value = e.target.checked;
    setChecked(value);

    if (checked) {
      disableMutation(payload).then(handleApiResponse);

      return;
    }

    enableMutation(payload).then(handleApiResponse);
  };

  return {
    change,
    isMutating: isDisableMutating || isEnableMutating,
    checked
  };
};

export default useStatus;
