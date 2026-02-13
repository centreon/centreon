import {
  MoreHoriz as MoreIcon,
  Share as ShareIcon,
  PersonRemove as UnShareIcon
} from '@mui/icons-material';
import { Box } from '@mui/material';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import { useIsFetching, useQueryClient } from '@tanstack/react-query';
import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import { resource } from '../../../../../api/models';
import { useDashboardUserPermissions } from '../../../DashboardUserPermissions/useDashboardUserPermissions';
import FavoriteAction from '../../Actions/favoriteAction';
import {
  labelMoreActions,
  labelShareWithContacts,
  labelUnshare
} from '../../translatedLabels';
import { useColumnStyles } from '../useColumnStyles';
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
        <IconButton onClick={openAskBeforeRevoke} title={t(labelUnshare)}>
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
          isFetching={isFetchingListing > 0}
          refetch={refetch}
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
        isFetching={isFetchingListing > 0}
        refetch={refetch}
      />
      <IconButton
        ariaLabel={t(labelShareWithContacts)}
        onClick={editAccessRights}
        title={t(labelShareWithContacts)}
      >
        <ShareIcon className={classes.icon} />
      </IconButton>
      <IconButton
        ariaLabel={t(labelMoreActions)}
        onClick={openMoreActions}
        title={t(labelMoreActions)}
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
