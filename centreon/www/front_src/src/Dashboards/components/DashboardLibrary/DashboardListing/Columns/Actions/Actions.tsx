import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  Share as ShareIcon,
  PersonRemove as UnShareIcon,
  MoreHoriz as MoreIcon
} from '@mui/icons-material';
import { Box } from '@mui/material';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import {
  labelMoreActions,
  labelUnshare,
  labelShareWithContacts
} from '../../translatedLabels';
import { DashboardRole } from '../../../../../api/models';
import { useColumnStyles } from '../useColumnStyles';

import useActions from './useActions';
import MoreActions from './MoreActions';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useColumnStyles();
  const { ownRole } = row;
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
      <IconButton title={t(labelUnshare)} onClick={openAskBeforeRevoke}>
        <UnShareIcon className={classes.icon} />
      </IconButton>
    );
  }

  if (equals(ownRole, DashboardRole.viewer)) {
    return <Box className={classes.line}>-</Box>;
  }

  return (
    <Box className={classes.actions}>
      <IconButton
        ariaLabel={t(labelShareWithContacts)}
        title={t(labelShareWithContacts)}
        onClick={editAccessRights}
      >
        <ShareIcon className={classes.icon} />
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
