import { IconButton } from '@centreon/ui';
import { platformFeaturesAtom, userAtom } from '@centreon/ui-context';
import { DeleteOutline } from '@mui/icons-material';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals, isNotNil, pick } from 'ramda';
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

  const { isAdmin } = useAtomValue(userAtom);
  const { isCloudPlatform } = useAtomValue(platformFeaturesAtom);
  const hasCentral = (
    isNotNil(row.internalListingParentId)
      ? row.internalListingParentRow?.pollers
      : row?.pollers
  )?.some((poller) => equals(poller?.isCentral, true));

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

  if (!isAdmin && isCloudPlatform && hasCentral) {
    return;
  }

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
