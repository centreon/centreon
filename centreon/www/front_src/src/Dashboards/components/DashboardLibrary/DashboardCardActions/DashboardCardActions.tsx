import { useTranslation } from 'react-i18next';

import { Menu } from '@mui/material';
import {
  Delete as DeleteIcon,
  Settings as SettingsIcon,
  Share as ShareIcon,
  ContentCopy as DuplicateIcon,
  MoreHoriz as MoreIcon
} from '@mui/icons-material';
import PlayArrowOutlinedIcon from '@mui/icons-material/PlayArrowOutlined';

import {
  ActionsList,
  ActionsListActionDivider,
  IconButton
} from '@centreon/ui';

import { Dashboard } from '../../../api/models';
import {
  labelAddToPlaylist,
  labelDelete,
  labelDuplicate,
  labelShareWithContacts
} from '../../../translatedLabels';
import {
  labelEditProperties,
  labelMoreActions
} from '../DashboardListing/translatedLabels';

import { useStyles } from './DashboardCardActions.styles';
import useDashboardCardActions from './useDashboardCardActions';

interface Props {
  dashboard: Dashboard;
}

const DashboardCardActions = ({ dashboard }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const {
    moreActionsOpen,
    hasIEEEInstalled,
    openDeleteModal,
    openDuplicateModal,
    openEditAccessRightModal,
    openEditModal,
    openMoreActions,
    closeMoreActions,
    openAddToPlaylistModal
  } = useDashboardCardActions({ dashboard });

  const labels = {
    labelAddToPlaylist: t(labelAddToPlaylist),
    labelDelete: t(labelDelete),
    labelDuplicate: t(labelDuplicate),
    labelEditProperties: t(labelEditProperties),
    labelMoreActions: t(labelMoreActions),
    labelShareWithContacts: t(labelShareWithContacts)
  };

  return (
    <div className={classes.container}>
      <IconButton
        ariaLabel={labels.labelShareWithContacts}
        title={labels.labelShareWithContacts}
        onClick={openEditAccessRightModal}
      >
        <ShareIcon fontSize="small" />
      </IconButton>
      <IconButton
        ariaLabel={labels.labelMoreActions}
        title={labels.labelMoreActions}
        onClick={openMoreActions}
      >
        <MoreIcon />
      </IconButton>
      <Menu
        anchorEl={moreActionsOpen}
        open={Boolean(moreActionsOpen)}
        onClose={closeMoreActions}
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
            ...(hasIEEEInstalled
              ? [
                  ActionsListActionDivider.divider,
                  {
                    Icon: PlayArrowOutlinedIcon,
                    label: labels.labelAddToPlaylist,
                    onClick: openAddToPlaylistModal
                  }
                ]
              : []),
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
