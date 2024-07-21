import { useTranslation } from 'react-i18next';

import {
  SettingsOutlined as SettingsIcon,
  MoreHoriz as MoreIcon
} from '@mui/icons-material';
import { Box } from '@mui/material';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import {
  labelMoreActions,
  labelEditConnectorConfiguration
} from '../../../translatedLabels';
import { useColumnStyles } from '../useColumnsStyles';

import useActions from './useActions';
import MoreActions from './MoreActions';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useColumnStyles();
  const {
    closeMoreActions,
    moreActionsOpen,
    openMoreActions,
    editConnectorConfiguration
  } = useActions(row);

  return (
    <Box className={classes.actions}>
      <IconButton
        ariaLabel={t(labelEditConnectorConfiguration)}
        title={t(labelEditConnectorConfiguration)}
        onClick={editConnectorConfiguration}
      >
        <SettingsIcon className={classes.icon} />
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
