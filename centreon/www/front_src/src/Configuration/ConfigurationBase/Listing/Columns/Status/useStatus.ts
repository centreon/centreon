import { useAtomValue } from 'jotai';
import { complement, isNotEmpty, propEq } from 'ramda';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { ResponseError, useBulkResponse } from '@centreon/ui';
import { capitalize } from '@mui/material';
import { useDisable, useEnable } from '../../../api';

import { configurationAtom } from '../../../atoms';

import {
  labelFailedToDisableResources,
  labelFailedToDisableSomeResources,
  labelFailedToEnableResources,
  labelFailedToEnableSomeResources,
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

  const handleBulkResponse = useBulkResponse();

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

  const labelErrorMessage = checked
    ? t(labelFailedToDisableResources(labelResourceType))
    : t(labelFailedToEnableResources(labelResourceType));

  const labelWarningMessage = checked
    ? t(labelFailedToDisableSomeResources)
    : t(labelFailedToEnableSomeResources);

  const handleApiResponse = (response) => {
    const { isError, results } = response as ResponseError;

    const failedResponses = results?.filter(complement(propEq(204, 'status')));

    if (isError || isNotEmpty(failedResponses)) {
      setChecked(checked);

      return;
    }

    handleBulkResponse({
      data: results,
      labelWarning: labelWarningMessage,
      labelFailed: labelErrorMessage,
      labelSuccess: labelSuccessMessage,
      items: [row.id]
    });
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
