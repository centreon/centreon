import { useTranslation } from 'react-i18next';

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

import { Dashboard } from '../../../api/models';
import {
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
