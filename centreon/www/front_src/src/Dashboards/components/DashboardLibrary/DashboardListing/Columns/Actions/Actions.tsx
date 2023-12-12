import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import ShareIcon from '@mui/icons-material/Share';
import SettingsIcon from '@mui/icons-material/SettingsOutlined';
import UnShareIcon from '@mui/icons-material/PersonRemove';
import { Box } from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import {
  labelDelete,
  labelEditProperties,
  labelShare,
  labelUnshare
} from '../../translatedLabels';
import { DashboardRole } from '../../../../../api/models';
import { useColumnStyles } from '../useColumnStyles';

import useActions from './useActions';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useColumnStyles();

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
    },
    {
      Icon: DeleteIcon,
      label: t(labelDelete),
      onClick: deleteDashboard
    }
  ];

  if (isNestedRow) {
    return (
      <IconButton title={t(labelUnshare)} onClick={() => undefined}>
        <UnShareIcon className={classes.icon} />
      </IconButton>
    );
  }

  if (equals(row?.ownRole, DashboardRole.viewer)) {
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
    </Box>
  );
};

export default Actions;
