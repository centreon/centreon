import { useAtomValue } from 'jotai';
import { complement, equals, isNotEmpty, propEq } from 'ramda';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import {
  Method,
  ResponseError,
  useBulkResponse,
  useSnackbar
} from '@centreon/ui';
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

  const { showSuccessMessage } = useSnackbar();

  const configuration = useAtomValue(configurationAtom);
  const resourceType = configuration?.resourceType;
  const method = configuration?.api?.methods?.disable as Method;

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

  const labelSuccessMessage = t(
    (checked ? labelResourceDisabled : labelResourceEnabled)(labelResourceType)
  );

  const labelErrorMessage = t(
    (checked ? labelFailedToDisableResources : labelFailedToEnableResources)(
      labelResourceType
    )
  );

  const labelWarningMessage = t(
    checked
      ? labelFailedToDisableSomeResources
      : labelFailedToEnableSomeResources
  );

  const handleApiResponse = (response) => {
    const { isError, results } = response as ResponseError;

    if (isError) {
      setChecked(checked);

      return;
    }

    if (equals(method, Method.PATCH)) {
      showSuccessMessage(labelSuccessMessage);

      return;
    }

    const failedResponses = results?.filter(complement(propEq(204, 'status')));

    if (isNotEmpty(failedResponses)) {
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
