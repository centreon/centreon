import { IconButton } from '@centreon/ui/components';
import { DeleteOutline } from '@mui/icons-material';
import { useSetAtom } from 'jotai';
import { isNotNil, pick } from 'ramda';
import { useTranslation } from 'react-i18next';
import { itemToDeleteAtom } from '../../atoms';
import { AgentConfigurationListing } from '../../models';
import { labelDelete } from '../../translatedLabels';

interface Props {
  row: AgentConfigurationListing & {
    internalListingParentId?: number;
    internalListingParentRow: AgentConfigurationListing;
  };
}

const Action = ({ row }: Props): JSX.Element => {
  const { t } = useTranslation();

  const setItemToDelete = useSetAtom(itemToDeleteAtom);

  const askBeforeDelete = (): void => {
    setItemToDelete({
      agent: isNotNil(row.internalListingParentId)
        ? {
            id: row?.internalListingParentRow?.id,
            name: row?.internalListingParentRow?.name
          }
        : pick(['id', 'name'], row),
      poller: isNotNil(row.internalListingParentId)
        ? pick(['id', 'name'], row)
        : undefined
    });
  };

  return (
    <IconButton
      size="small"
      icon={<DeleteOutline fontSize="small" color="error" />}
      onClick={askBeforeDelete}
      title={t(labelDelete)}
    />
  );
};

export default Action;
