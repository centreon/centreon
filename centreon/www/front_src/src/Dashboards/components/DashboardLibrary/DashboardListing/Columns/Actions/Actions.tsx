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

import { useIsFetching, useQueryClient } from '@tanstack/react-query';
import { useCallback } from 'react';
import { resource } from '../../../../../api/models';
import FavoriteAction from '../../Actions/favoriteAction';
import MoreActions from './MoreActions';
import useActions from './useActions';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useColumnStyles();
  const queryClient = useQueryClient();
  const { hasEditPermission } = useDashboardUserPermissions();
  const isFetchingListing = useIsFetching({ queryKey: [resource.dashboards] });

  const refetch = useCallback(() => {
    queryClient.invalidateQueries({ queryKey: [resource.dashboards] });
  }, []);

  const {
    isNestedRow,
    editAccessRights,
    openAskBeforeRevoke,
    closeMoreActions,
    moreActionsOpen,
    openMoreActions
  } = useActions(row);

  if (isNestedRow) {
    return (
      <div className={classes.spacing}>
        <IconButton title={t(labelUnshare)} onClick={openAskBeforeRevoke}>
          <UnShareIcon className={classes.icon} />
        </IconButton>
      </div>
    );
  }

  if (!hasEditPermission(row)) {
    return (
      <div className={classes.actions}>
        <FavoriteAction
          dashboardId={row.id}
          isFavorite={row?.isFavorite}
          refetch={refetch}
          isFetching={isFetchingListing > 0}
        />
        <Box className={classes.line}>-</Box>
      </div>
    );
  }

  return (
    <Box className={classes.actions}>
      <FavoriteAction
        dashboardId={row.id}
        isFavorite={row?.isFavorite}
        refetch={refetch}
        isFetching={isFetchingListing > 0}
      />
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
    </Box>
  );
};

export default Actions;
