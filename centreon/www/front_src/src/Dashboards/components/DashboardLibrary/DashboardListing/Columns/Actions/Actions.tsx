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

import useActions from './useActions';
import DeleteDashboard from './DeleteDashboard';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useColumnStyles();
  const { name: dashboardName, ownRole } = row;
  const { editDashboard, isNestedRow, deleteDashboard, editAccessRights } =
    useActions(row);

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
      <IconButton title={t(labelUnshare)} onClick={() => undefined}>
        <UnShareIcon className={classes.icon} />
      </IconButton>
    );
  }

  if (equals(ownRole, DashboardRole.viewer)) {
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

      <DeleteDashboard
        dashboardName={dashboardName}
        deleteDashboard={deleteDashboard}
      />
    </Box>
  );
};

export default Actions;
