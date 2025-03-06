import { Box, CircularProgress, Typography } from '@mui/material';
import { useAtom } from 'jotai';
import { useCallback, useRef } from 'react';
import { Button } from '../Button';
import { Modal } from '../Modal';
import { itemToDeleteAtom } from './atoms';
import { useDeleteItem } from './hooks/useDeleteItem';
import { DeleteItem, ItemToDelete } from './models';
import { isAFunction } from './utils';

const DeleteModal = <TData extends { id: number; name: string }>({
  labels,
  deleteEndpoint,
  listingQueryKey,
  modalSize = 'medium'
}: DeleteItem & {
  listingQueryKey: string;
}): JSX.Element => {
  const itemToDeleteRef = useRef<ItemToDelete | null>(null);

  const [itemToDelete, setItemToDelete] = useAtom(itemToDeleteAtom);

  const { isMutating, deleteItem } = useDeleteItem({
    deleteEndpoint,
    listingQueryKey,
    successMessage: labels.successMessage
  });

  const isOpen = Boolean(itemToDelete);

  const close = useCallback(() => {
    setItemToDelete(null);
  }, []);

  const confirm = useCallback(() => {
    deleteItem(itemToDeleteRef.current).then(close);
  }, [itemToDeleteRef.current]);

  if (isOpen) {
    itemToDeleteRef.current = itemToDelete;
  }

  return (
    <Modal open={isOpen} onClose={close} size={modalSize}>
      <Modal.Header>
        {isAFunction(labels.title)
          ? labels.title(itemToDeleteRef.current as TData)
          : labels.title}
      </Modal.Header>
      <Modal.Body>
        <Typography>
          {isAFunction(labels.description)
            ? labels.description(itemToDeleteRef.current as TData)
            : labels.description}
        </Typography>
      </Modal.Body>
      <Box
        sx={{
          display: 'flex',
          flexDirection: 'row',
          gap: 2,
          justifyContent: 'flex-end'
        }}
      >
        {isMutating && <CircularProgress size={20} />}
        <Button variant="ghost" onClick={close} disabled={isMutating}>
          {labels.cancel}
        </Button>
        <Button isDanger onClick={confirm} disabled={isMutating}>
          {labels.confirm}
        </Button>
      </Box>
    </Modal>
  );
};

export default DeleteModal;
