import { capitalize } from '@mui/material';
import pluralize from 'pluralize';

import { ResponseError, useBulkResponse } from '@centreon/ui';

import { useAtom, useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { useDisable, useEnable } from '../../../api';
import { configurationAtom } from '../../../atoms';
import { selectedRowsAtom } from '../../atoms';

import {
  labelFailedToDisableResources,
  labelFailedToDisableSomeResources,
  labelFailedToEnableResources,
  labelFailedToEnableSomeResources,
  labelResourceDisabled,
  labelResourceEnabled
} from '../../../translatedLabels';

interface UseChangeStatus {
  isMutating: boolean;
  enable: () => void;
  disable: () => void;
}

const useChangeStatus = (): UseChangeStatus => {
  const { t } = useTranslation();
  const handleBulkResponse = useBulkResponse();

  const [selectedRows, setSelectedRows] = useAtom(selectedRowsAtom);
  const configuration = useAtomValue(configurationAtom);

  const resourceType = configuration?.resourceType;
  const selectedRowsIds = selectedRows?.map((row) => row.id);
  const count = selectedRowsIds.length;

  const labelResourceType = pluralize(
    capitalize(resourceType as string),
    count
  );

  const resetSelectedRows = (): void => setSelectedRows([]);

  const { enableMutation, isMutating: isEnableMutating } = useEnable();
  const { disableMutation, isMutating: isDisableMutating } = useDisable();

  const enable = (): void => {
    enableMutation({ ids: selectedRowsIds }).then((response) => {
      const { isError, results } = response as ResponseError;

      if (isError) {
        return;
      }

      handleBulkResponse({
        data: results,
        labelWarning: t(labelFailedToEnableSomeResources),
        labelFailed: t(labelFailedToEnableResources(labelResourceType)),
        labelSuccess: t(labelResourceEnabled(labelResourceType)),
        items: selectedRows
      });

      resetSelectedRows();
    });
  };

  const disable = (): void => {
    disableMutation({ ids: selectedRowsIds }).then((response) => {
      const { isError, results } = response as ResponseError;

      if (isError) {
        return;
      }

      handleBulkResponse({
        data: results,
        labelWarning: t(labelFailedToDisableSomeResources),
        labelFailed: t(labelFailedToDisableResources(labelResourceType)),
        labelSuccess: t(labelResourceDisabled(labelResourceType)),
        items: selectedRows
      });

      resetSelectedRows();
    });
  };

  return {
    isMutating: isEnableMutating || isDisableMutating,
    enable,
    disable
  };
};

export default useChangeStatus;
