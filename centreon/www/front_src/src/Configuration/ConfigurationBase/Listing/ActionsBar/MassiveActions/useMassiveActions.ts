import { capitalize } from '@mui/material';
import pluralize from 'pluralize';

import { ResponseError, useBulkResponse } from '@centreon/ui';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { configurationAtom } from '../../../../atoms';
import { useDisable, useEnable } from '../../../api';
import {
  resourcesToDeleteAtom,
  resourcesToDuplicateAtom,
  selectedRowsAtom
} from '../../atoms';

import { map, pick } from 'ramda';
import {
  labelFailedToDisableResources,
  labelFailedToDisableSomeResources,
  labelFailedToEnableResources,
  labelFailedToEnableSomeResources,
  labelResourceEnabled
} from '../../../translatedLabels';

interface UseMassiveActions {
  isMutating: boolean;
  enable: () => void;
  disable: () => void;
  openDeleteModal: () => void;
  openDuplicateModal: () => void;
}

const useMassiveActions = (): UseMassiveActions => {
  const { t } = useTranslation();
  const handleBulkResponse = useBulkResponse();

  const [selectedRows, setSelectedRows] = useAtom(selectedRowsAtom);
  const configuration = useAtomValue(configurationAtom);

  const setResourcesToDelete = useSetAtom(resourcesToDeleteAtom);
  const setResourcesToDuplicate = useSetAtom(resourcesToDuplicateAtom);

  const resourceType = configuration?.resourceType;
  const selectedRowsIds = selectedRows?.map((row) => row.id);
  const selectedRowsEntities = map(pick(['id', 'name']), selectedRows);

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
        labelSuccess: t(labelResourceEnabled(labelResourceType)),
        items: selectedRows
      });

      resetSelectedRows();
    });
  };

  const openDeleteModal = (): void =>
    setResourcesToDelete(selectedRowsEntities);
  const openDuplicateModal = (): void =>
    setResourcesToDuplicate(selectedRowsEntities);

  return {
    isMutating: isEnableMutating || isDisableMutating,
    enable,
    disable,
    openDeleteModal,
    openDuplicateModal
  };
};

export default useMassiveActions;
