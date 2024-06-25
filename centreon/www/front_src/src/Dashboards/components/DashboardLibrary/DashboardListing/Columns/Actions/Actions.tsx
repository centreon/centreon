import { useTranslation } from 'react-i18next';

import {
  Share as ShareIcon,
  PersonRemove as UnShareIcon,
  MoreHoriz as MoreIcon
} from '@mui/icons-material';
import { Box } from '@mui/material';
import FavoriteIcon from '@mui/icons-material/Favorite';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import {
  labelMoreActions,
  labelUnshare,
  labelShareWithContacts
} from '../../translatedLabels';
import { useColumnStyles } from '../useColumnStyles';
import { useDashboardUserPermissions } from '../../../DashboardUserPermissions/useDashboardUserPermissions';

import useActions from './useActions';
import MoreActions from './MoreActions';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useColumnStyles();
  const { hasEditPermission } = useDashboardUserPermissions();
  const {
    isNestedRow,
    editAccessRights,
    openAskBeforeRevoke,
    closeMoreActions,
    moreActionsOpen,
    openMoreActions,
    isFavorite
  } = useActions(row);

  if (isNestedRow) {
    return (
      <IconButton title={t(labelUnshare)} onClick={openAskBeforeRevoke}>
        <UnShareIcon className={classes.icon} />
      </IconButton>
    );
  }

  if (!hasEditPermission(row)) {
    return <Box className={classes.line}>-</Box>;
  }

  return (
    <Box className={classes.actions}>
      <IconButton
        ariaLabel={t(labelShareWithContacts)}
        title={t(labelShareWithContacts)}
        onClick={editAccessRights}
      >
        <ShareIcon className={classes.icon} />
      </IconButton>
      <FavoriteIcon
        color={isFavorite ? 'success' : 'disabled'}
        fontSize="small"
      />
      <IconButton
        ariaLabel={t(labelMoreActions)}
        title={t(labelMoreActions)}
        onClick={openMoreActions}
      >
        <MoreIcon />
      </IconButton>

      <MoreActions
        anchor={moreActionsOpen}
        close={closeMoreActions}
        row={row}
      />
    </Box>
  );
};

export default Actions;
