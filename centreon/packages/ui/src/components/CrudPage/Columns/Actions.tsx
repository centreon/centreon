import DeleteOutline from '@mui/icons-material/DeleteOutline';
import { Box } from '@mui/material';
import { useAtomValue, useSetAtom } from 'jotai';
import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { IconButton } from '../../Button';
import { isDeleteEnabledAtom, itemToDeleteAtom } from '../atoms';

interface Props<TData> {
  row: TData & {
    internalListingParentId?: number;
    internalListingParentRow: TData;
  };
}

const labelDelete = 'Delete';

const Actions = <TData extends { id: number; name: string }>({
  row
}: Props<TData>): JSX.Element => {
  const { t } = useTranslation();
  const isDeleteEnabled = useAtomValue(isDeleteEnabledAtom);
  const setItemToDelete = useSetAtom(itemToDeleteAtom);

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

  return (
    <Box sx={{ display: 'flex', flexDirection: 'row', gap: 2 }}>
      {isDeleteEnabled && (
        <IconButton
          size="small"
          icon={<DeleteOutline fontSize="small" color="error" />}
          onClick={askBeforeDelete}
          title={t(labelDelete)}
        />
      )}
    </Box>
  );
};

export default Actions;
