import { IconButton } from '@centreon/ui/components';
import { DeleteOutline } from '@mui/icons-material';
import { useSetAtom } from 'jotai';
import { pick } from 'ramda';
import { itemToDeleteAtom } from '../../atoms';
import { AgentConfigurationListing } from '../../models';

interface Props {
  row: AgentConfigurationListing & {
    internalListingParentId?: number;
    internalListingParentRow: AgentConfigurationListing;
  };
}

const Action = ({ row }: Props): JSX.Element => {
  const setItemToDelete = useSetAtom(itemToDeleteAtom);

  const askBeforeDelete = (): void => {
    setItemToDelete({
      agent: row.internalListingParentId
        ? pick(['id', 'name'], row.internalListingParentRow)
        : pick(['id', 'name'], row),
      poller: row.internalListingParentId
        ? pick(['id', 'name'], row)
        : undefined
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
