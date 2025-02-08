import { pipe } from 'ramda';
import { useTranslation } from 'react-i18next';

import { ToggleOffRounded, ToggleOnRounded } from '@mui/icons-material';
import { Menu } from '@mui/material';

import {
  ActionsList,
  ActionsListActionDivider,
  Method,
  ResponseError,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import {
  labelDisable,
  labelEnable,
  labelHostGroupsDisabled,
  labelHostGroupsEnabled
} from '../../../translatedLabels';

import { useQueryClient } from '@tanstack/react-query';
import {
  bulkDisableHostGroupEndpoint,
  bulkEnableHostGroupEndpoint
} from '../../../api/endpoints';

interface Props {
  anchor: HTMLElement | null;
  close: () => void;
  resetSelectedRows: () => void;
  selectedRowsIds: Array<number>;
}

const MoreActions = ({
  close,
  anchor,
  selectedRowsIds,
  resetSelectedRows
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const { showSuccessMessage } = useSnackbar();

  const queryClient = useQueryClient();

  const { mutateAsync: enableHostGroupsRequest } = useMutationQuery({
    getEndpoint: () => bulkEnableHostGroupEndpoint,
    method: Method.POST,
    onSuccess: () => {
      resetSelectedRows();
      showSuccessMessage(t(labelHostGroupsEnabled));
      queryClient.invalidateQueries({ queryKey: ['listHostGroups'] });
    }
  });

  const { mutateAsync: disableHostGroupsRequest, isMutating } =
    useMutationQuery({
      getEndpoint: () => bulkDisableHostGroupEndpoint,
      method: Method.POST,
      onSuccess: () => {
        resetSelectedRows();
        showSuccessMessage(t(labelHostGroupsDisabled));
        queryClient.invalidateQueries({ queryKey: ['listHostGroups'] });
      }
    });

  const enable = (): Promise<object | ResponseError> =>
    enableHostGroupsRequest({ payload: { ids: selectedRowsIds } });

  const disable = (): Promise<object | ResponseError> =>
    disableHostGroupsRequest({ payload: { ids: selectedRowsIds } });

  return (
    <Menu anchorEl={anchor} open={Boolean(anchor)} onClose={close}>
      <ActionsList
        actions={[
          {
            Icon: ToggleOnRounded,
            label: t(labelEnable),
            onClick: pipe(enable, close),
            variant: 'success',
            disable: isMutating
          },
          ActionsListActionDivider.divider,
          {
            Icon: ToggleOffRounded,
            label: t(labelDisable),
            onClick: pipe(disable, close),
            disable: isMutating,
            variant: 'error'
          }
        ]}
      />
    </Menu>
  );
};

export default MoreActions;
