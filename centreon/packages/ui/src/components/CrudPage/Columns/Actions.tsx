import { EditOutlined } from '@mui/icons-material';
import DeleteOutline from '@mui/icons-material/DeleteOutline';
import { Box } from '@mui/material';

import { useAtomValue, useSetAtom } from 'jotai';
import { isNil } from 'ramda';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import { IconButton } from '../../Button';
import {
  canDeleteSubItemsAtom,
  itemToDeleteAtom,
  openFormModalAtom
} from '../atoms';

interface Props<TData> {
  row: TData & {
    internalListingParentId?: number;
    internalListingParentRow: TData;
  };
}

const labelDelete = 'Delete';
const labelUpdate = 'Update';

const Actions = <TData extends { id: number; name: string }>({
  row
}: Props<TData>): JSX.Element => {
  const { t } = useTranslation();
  const canDeleteSubItems = useAtomValue(canDeleteSubItemsAtom);
  const setItemToDelete = useSetAtom(itemToDeleteAtom);
  const setOpenFormModal = useSetAtom(openFormModalAtom);

  const askBeforeDelete = (): void => {
    setItemToDelete({
      id: row.id,
      name: row.name,
      parent: isNil(row.internalListingParentRow)
        ? undefined
        : {
            id: row.internalListingParentRow.id,
            name: row.internalListingParentRow.name
          }
    });
  };

  const updateRow = useCallback(
    () => setOpenFormModal(row.id),
    [row.id, setOpenFormModal]
  );

  return (
    <Box
      sx={{
        display: 'flex',
        flexDirection: 'row',
        gap: 1,
        justifyContent: 'flex-end',
        width: '100%'
      }}
    >
      {isNil(row.internalListingParentRow) && (
        <IconButton
          data-testid={
            row.internalListingParentRow
              ? `edit-${row.internalListingParentRow.id}-${row.id}`
              : `edit-${row.id}`
          }
          icon={<EditOutlined color="primary" fontSize="small" />}
          onClick={updateRow}
          size="small"
          title={t(labelUpdate)}
        />
      )}
      {(canDeleteSubItems || isNil(row.internalListingParentRow)) && (
        <IconButton
          data-testid={
            row.internalListingParentRow
              ? `delete-${row.internalListingParentRow.id}-${row.id}`
              : `delete-${row.id}`
          }
          icon={<DeleteOutline color="error" fontSize="small" />}
          onClick={askBeforeDelete}
          size="small"
          title={t(labelDelete)}
        />
      )}
    </Box>
  );
};

export default Actions;
