import { IconButton } from '@centreon/ui/components';
import { DeleteOutline } from '@mui/icons-material';
import { useSetAtom } from 'jotai';
import { itemToDeleteAtom } from '../../atoms';
import { AgentConfigurationListing } from '../../models';

interface Props {
  row: AgentConfigurationListing & { internalListingParentId?: number };
}

const Action = ({ row }: Props): JSX.Element => {
  const setItemToDelete = useSetAtom(itemToDeleteAtom);

  const askBeforeDelete = (): void => {
    setItemToDelete({
      id: row.internalListingParentId || row.id,
      pollerId: row.internalListingParentId ? row.id : undefined
    });
  };

  return (
    <IconButton
      size="small"
      icon={<DeleteOutline fontSize="small" color="error" />}
      onClick={askBeforeDelete}
    />
  );
};

export default Action;
