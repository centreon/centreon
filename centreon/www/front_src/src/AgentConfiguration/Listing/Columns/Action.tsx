import { IconButton } from '@centreon/ui';
import { DeleteOutline } from '@mui/icons-material';
import { useSetAtom } from 'jotai';
import { isNotNil, pick } from 'ramda';
import { useTranslation } from 'react-i18next';
import { itemToDeleteAtom } from '../../atoms';
import { AgentConfigurationListing } from '../../models';
import { labelDelete } from '../../translatedLabels';
import { useStyles } from './Action.styles';

interface Props {
  row: AgentConfigurationListing & {
    internalListingParentId?: number;
    internalListingParentRow: AgentConfigurationListing;
  };
}

const Action = ({ row }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const setItemToDelete = useSetAtom(itemToDeleteAtom);

  const askBeforeDelete = (): void => {
    setItemToDelete({
      agent: isNotNil(row.internalListingParentId)
        ? pick(['id', 'name'], row.internalListingParentRow)
        : pick(['id', 'name'], row),
      poller: isNotNil(row.internalListingParentId)
        ? pick(['id', 'name'], row)
        : undefined
    });
  };

  return (
    <IconButton
      ariaLabel={t(labelDelete)}
      title={t(labelDelete)}
      onClick={askBeforeDelete}
      className={classes.removeButton}
    >
      <DeleteOutline className={classes.removeIcon} />
    </IconButton>
  );
};

export default Action;
