import { useTranslation } from 'react-i18next';

import {
  DeleteOutlined as DeleteIcon,
  SettingsOutlined as SettingsIcon
} from '@mui/icons-material';
import { Box } from '@mui/material';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import {
  labelDelete,
  labelEditConnectorConfiguration
} from '../../../translatedLabels';
import { useColumnStyles } from '../useColumnsStyles';

import useActions from './useActions';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useColumnStyles();

  const { openDeleteModal, openEditDialog } = useActions(row);

  return (
    <Box className={classes.actions}>
      <IconButton
        ariaLabel={t(labelEditConnectorConfiguration)}
        title={t(labelEditConnectorConfiguration)}
        onClick={openEditDialog}
      >
        <SettingsIcon className={classes.icon} />
      </IconButton>
      <IconButton
        ariaLabel={t(labelDelete)}
        title={t(labelDelete)}
        onClick={openDeleteModal}
        className={classes.removeButton}
      >
        <DeleteIcon className={classes.removeIcon} />
      </IconButton>
    </Box>
  );
};

export default Actions;
