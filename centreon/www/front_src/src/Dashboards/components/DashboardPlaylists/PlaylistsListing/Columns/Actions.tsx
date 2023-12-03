import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import ShareIcon from '@mui/icons-material/Share';
import SettingsIcon from '@mui/icons-material/SettingsOutlined';
import MoreIcon from '@mui/icons-material/MoreHoriz';
import UnShareIcon from '@mui/icons-material/PersonRemoveOutlined';
import { Box } from '@mui/material';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import { labelUnshare } from '../translatedLabels';

import { useColumnStyles } from './useColumnStyles';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useColumnStyles();

  const isNestedRow = !isNil(row?.role);

  if (isNestedRow) {
    return (
      <IconButton title={t(labelUnshare)} onClick={() => undefined}>
        <UnShareIcon className={classes.icon} />
      </IconButton>
    );
  }

  return (
    <Box className={classes.actions}>
      <IconButton onClick={() => undefined}>
        <ShareIcon className={classes.icon} />
      </IconButton>
      <IconButton onClick={() => undefined}>
        <SettingsIcon className={classes.icon} />
      </IconButton>
      <IconButton onClick={() => undefined}>
        <MoreIcon />
      </IconButton>
    </Box>
  );
};

export default Actions;
