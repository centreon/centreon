import { useState } from 'react';

import { or } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';

import { Box } from '@mui/material';
import DeleteIcon from '@mui/icons-material/DeleteOutline';

import {
  IconButton,
  ResponseError,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import DeleteDialog from '../Dialogs/DeleteDialog';
import { labelDelete } from '../translatedLabels';

interface Props {
  fetchMethod?;
  fetchPayload?;
  getEndpoint?;
  labelFailed?;
  labelSuccess?;
  notificationName?;
  onSuccess?;
}

const DeleteButton = ({
  getEndpoint,
  onSuccess,
  labelSuccess,
  labelFailed,
  fetchMethod,
  fetchPayload,
  notificationName
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const queryClient = useQueryClient();

  const [dialogOpen, setDialogOpen] = useState(false);
  const { isMutating, mutateAsync } = useMutationQuery({
    defaultFailureMessage: t(labelFailed) as string,
    getEndpoint,
    method: fetchMethod
  });

  const onClick = (): void => setDialogOpen(true);
  const onCancel = (): void => setDialogOpen(false);

  const onConfirm = (): void => {
    mutateAsync(fetchPayload || {}).then((response) => {
      if ((response as ResponseError).isError) {
        return;
      }
      showSuccessMessage(t(labelSuccess));
      setDialogOpen(false);
      onSuccess?.();
      queryClient.invalidateQueries(['notifications']);
    });
  };

  return (
    <Box>
      <IconButton
        ariaLabel={t(labelDelete) as string}
        disabled={false}
        title={t(labelDelete) as string}
        onClick={onClick}
      >
        <DeleteIcon />
      </IconButton>
      <DeleteDialog
        isMutating={isMutating}
        notificationName={or(notificationName, '')}
        open={dialogOpen}
        onCancel={onCancel}
        onConfirm={onConfirm}
      />
    </Box>
  );
};

export default DeleteButton;
