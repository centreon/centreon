import {
  Delete as DeleteIcon,
  ContentCopy as DuplicateIcon,
  MoreHoriz as MoreIcon,
  Settings as SettingsIcon,
  Share as ShareIcon
} from '@mui/icons-material';
import { Menu } from '@mui/material';

import {
  ActionsList,
  ActionsListActionDivider,
  IconButton
} from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { Dashboard } from '../../../api/models';
import {
  labelDelete,
  labelDuplicate,
  labelShareWithContacts
} from '../../../translatedLabels';
import FavoriteAction from '../DashboardListing/Actions/favoriteAction';
import {
  labelEditProperties,
  labelMoreActions
} from '../DashboardListing/translatedLabels';
import { useStyles } from './DashboardCardActions.styles';
import useDashboardCardActions from './useDashboardCardActions';

interface Props {
  dashboard: Dashboard;
  refetch?: () => void;
  isFetchingListing: boolean;
}

const DashboardCardActions = ({
  dashboard,
  refetch,
  isFetchingListing
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const {
    moreActionsOpen,
    openDeleteModal,
    openDuplicateModal,
    openEditAccessRightModal,
    openEditModal,
    openMoreActions,
    closeMoreActions
  } = useDashboardCardActions({ dashboard });

  const labels = {
    labelDelete: t(labelDelete),
    labelDuplicate: t(labelDuplicate),
    labelEditProperties: t(labelEditProperties),
    labelMoreActions: t(labelMoreActions),
    labelShareWithContacts: t(labelShareWithContacts)
  };

  return (
    <div className={classes.container}>
      <FavoriteAction
        dashboardId={dashboard.id as number}
        isFavorite={dashboard?.isFavorite as boolean}
        isFetching={isFetchingListing}
        refetch={refetch}
      />
      <IconButton
        ariaLabel={labels.labelShareWithContacts}
        onClick={openEditAccessRightModal}
        title={labels.labelShareWithContacts}
      >
        <ShareIcon fontSize="small" />
      </IconButton>
      <IconButton
        ariaLabel={labels.labelMoreActions}
        onClick={openMoreActions}
        title={labels.labelMoreActions}
      >
        <MoreIcon />
      </IconButton>
      <Menu
        anchorEl={moreActionsOpen}
        onClose={closeMoreActions}
        open={Boolean(moreActionsOpen)}
      >
        <ActionsList
          actions={[
            {
              Icon: SettingsIcon,
              label: labels.labelEditProperties,
              onClick: openEditModal
            },
            ActionsListActionDivider.divider,
            {
              Icon: DuplicateIcon,
              label: labels.labelDuplicate,
              onClick: openDuplicateModal
            },
            ActionsListActionDivider.divider,
            {
              Icon: DeleteIcon,
              label: labels.labelDelete,
              onClick: openDeleteModal,
              variant: 'error'
            }
          ]}
        />
      </Menu>
    </div>
  );
};

export default DashboardCardActions;
