import { ResponseError, useSnackbar } from '@centreon/ui';

import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import {
  labelHostGroupsDisabled,
  labelHostGroupsEnabled
} from '../../../translatedLabels';
import { useDisable, useEnable } from '../../api';
import { selectedRowsAtom } from '../../atoms';

interface UseChangeStatus {
  isMutating: boolean;
  enable: () => void;
  disable: () => void;
}

const useChangeStatus = (): UseChangeStatus => {
  const { t } = useTranslation();

  const { showSuccessMessage } = useSnackbar();

  const [selectedRows, setSelectedRows] = useAtom(selectedRowsAtom);
  const resetSelectedRows = (): void => setSelectedRows([]);

  const selectedRowsIds = selectedRows?.map((row) => row.id);

  const { enableMutation, isMutating: isEnableMutating } = useEnable();
  const { disableMutation, isMutating: isDisableMutating } = useDisable();

  const enable = (): void => {
    enableMutation({ ids: selectedRowsIds }).then((response) => {
      const { isError } = response as ResponseError;
      if (isError) {
        return;
      }

      resetSelectedRows();
      showSuccessMessage(t(labelHostGroupsEnabled));
    });
  };

  const disable = (): void => {
    disableMutation({ ids: selectedRowsIds }).then((response) => {
      const { isError } = response as ResponseError;
      if (isError) {
        return;
      }

      resetSelectedRows();
      showSuccessMessage(t(labelHostGroupsDisabled));
    });
  };

  return {
    isMutating: isEnableMutating || isDisableMutating,
    enable,
    disable
  };
};

export default useChangeStatus;
