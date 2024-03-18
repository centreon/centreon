import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  Share as ShareIcon,
  SettingsOutlined as SettingsIcon,
  PersonRemove as UnShareIcon
} from '@mui/icons-material';
import { Box } from '@mui/material';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import {
  labelEditProperties,
  labelShare,
  labelUnshare
} from '../../translatedLabels';
import { DashboardRole } from '../../../../../api/models';
import { useColumnStyles } from '../useColumnStyles';
import { useDashboardUserPermissions } from '../../../DashboardUserPermissions/useDashboardUserPermissions';

import useActions from './useActions';
import DeleteDashboard from './DeleteDashboard';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useColumnStyles();
  const { editDashboard, isNestedRow, editAccessRights, openAskBeforeRevoke } =
    useActions(row);
  const { hasEditPermission } = useDashboardUserPermissions();

  const actions = [
    {
      Icon: ShareIcon,
      label: labelShare,
      onClick: editAccessRights
    },
    {
      Icon: SettingsIcon,
      label: labelEditProperties,
      onClick: editDashboard
    }
  ];

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
      {actions.map(({ label, Icon, onClick }) => {
        return (
          <IconButton
            ariaLabel={t(label)}
            key={label}
            title={t(label)}
            onClick={onClick}
          >
            <Icon className={classes.icon} />
          </IconButton>
        );
      })}

      <DeleteDashboard row={row} />
    </Box>
  );
};

export default Actions;
