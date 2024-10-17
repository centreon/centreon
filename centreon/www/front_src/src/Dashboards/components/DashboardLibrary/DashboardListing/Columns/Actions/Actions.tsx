import { useTranslation } from 'react-i18next';

import {
  MoreHoriz as MoreIcon,
  Share as ShareIcon,
  PersonRemove as UnShareIcon
} from '@mui/icons-material';
import { Box } from '@mui/material';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import { useDashboardUserPermissions } from '../../../DashboardUserPermissions/useDashboardUserPermissions';
import {
  labelMoreActions,
  labelShareWithContacts,
  labelUnshare
} from '../../translatedLabels';
import { useColumnStyles } from '../useColumnStyles';

import MoreActions from './MoreActions';
import useActions from './useActions';

import Favorite from '../../../../DashboardFavorite/Favorite';

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

  return (
    <Box className={classes.actions}>
      <Favorite isFavorite={isFavorite} dashboardId={row?.id} />
      {hasEditPermission(row) && (
        <>
          <IconButton
            ariaLabel={t(labelShareWithContacts)}
            title={t(labelShareWithContacts)}
            onClick={editAccessRights}
          >
            <ShareIcon className={classes.icon} />
          </IconButton>
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
        </>
      )}
    </Box>
  );
};

export default Actions;
