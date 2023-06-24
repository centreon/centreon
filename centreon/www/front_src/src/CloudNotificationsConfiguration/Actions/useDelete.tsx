import { useState } from 'react';

import { useAtomValue } from 'jotai';

import { Method } from '@centreon/ui';

import { selectedRowsAtom } from '../atom';
import useDeleteRequest from '../api/useDeleteRequest';

interface UseDeleteProps {
  fetchMethod?: Method;
  getEndpoint?: () => string;
  labelFailed?: string;
  labelSuccess?: string;
  onSuccess?: () => void;
  payload?: { ids: Array<number | string> };
}

interface UseDeleteState {
  dialogOpen: boolean;
  isMutating: boolean;
  onCancel: () => void;
  onClick: () => void;
  onConfirm: () => void;
}

const useDelete = ({
  getEndpoint,
  onSuccess,
  labelSuccess,
  labelFailed,
  fetchMethod,
  payload
}: UseDeleteProps): UseDeleteState => {
  const [dialogOpen, setDialogOpen] = useState(false);
  const selectedRows = useAtomValue(selectedRowsAtom);

  const { onConfirm, isMutating } = useDeleteRequest({
    fetchMethod,
    getEndpoint,
    labelFailed,
    labelSuccess,
    onSuccess,
    payload,
    selectedRows,
    setDialogOpen
  });

  const onClick = (): void => setDialogOpen(true);
  const onCancel = (): void => setDialogOpen(false);

  return {
    dialogOpen,
    isMutating,
    onCancel,
    onClick,
    onConfirm
  };
};

export default useDelete;
