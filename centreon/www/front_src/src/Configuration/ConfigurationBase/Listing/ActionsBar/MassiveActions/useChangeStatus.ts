import { ResponseError, useSnackbar } from '@centreon/ui';

import { useAtom, useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import {
  labelResourceDisabled,
  labelResourceEnabled
} from '../../../translatedLabels';
import { useDisable, useEnable } from '../../api';
import { selectedRowsAtom } from '../../atoms';

interface UseChangeStatus {
  isMutating: boolean;
  enable: () => void;
  disable: () => void;
}

import { capitalize } from '@mui/material';
import pluralize from 'pluralize';
import { configurationAtom } from '../../../../atoms';

const useChangeStatus = (): UseChangeStatus => {
  const { t } = useTranslation();

  const { showSuccessMessage } = useSnackbar();

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
      const { isError } = response as ResponseError;
      if (isError) {
        return;
      }

      resetSelectedRows();

      showSuccessMessage(t(labelResourceEnabled(labelResourceType)));
    });
  };

  const disable = (): void => {
    disableMutation({ ids: selectedRowsIds }).then((response) => {
      const { isError } = response as ResponseError;
      if (isError) {
        return;
      }

      resetSelectedRows();

      showSuccessMessage(t(labelResourceDisabled(labelResourceType)));
    });
  };

  return {
    isMutating: isEnableMutating || isDisableMutating,
    enable,
    disable
  };
};

export default useChangeStatus;
