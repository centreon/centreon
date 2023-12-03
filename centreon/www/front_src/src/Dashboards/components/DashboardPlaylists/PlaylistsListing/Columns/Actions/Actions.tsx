import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import ShareIcon from '@mui/icons-material/Share';
import SettingsIcon from '@mui/icons-material/SettingsOutlined';
import MoreIcon from '@mui/icons-material/MoreHoriz';
import UnShareIcon from '@mui/icons-material/PersonRemoveOutlined';
import { Box } from '@mui/material';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import { Role as RoleType } from '../../models';
import {
  labelMoreActions,
  labelSettings,
  labelShare,
  labelUnshare
} from '../../translatedLabels';
import { useColumnStyles } from '../useColumnStyles';

import MoreActions from './MoreActions';
import useActions from './useActions';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useColumnStyles();

  const {
    closeMoreActions,
    initializePlaylistConfiguration,
    moreActionsOpen,
    openMoreActions,
    isNestedRow
  } = useActions(row);

  const actions = [
    {
      Icon: ShareIcon,
      className: classes.icon,
      label: labelShare,
      onClick: (): void => undefined
    },
    {
      Icon: SettingsIcon,
      className: classes.icon,
      label: labelSettings,
      onClick: initializePlaylistConfiguration
    },
    {
      Icon: MoreIcon,
      label: labelMoreActions,
      onClick: openMoreActions
    }
  ];

  if (isNestedRow) {
    return (
      <IconButton title={t(labelUnshare)} onClick={() => undefined}>
        <UnShareIcon className={classes.icon} />
      </IconButton>
    );
  }

  if (equals(row?.ownRole, RoleType.Viewer)) {
    return <Box className={classes.line}>-</Box>;
  }

  return (
    <Box className={classes.actions}>
      {actions.map(({ label, Icon, onClick, className }) => {
        return (
          <IconButton ariaLabel={t(label)} key={label} onClick={onClick}>
            <Icon className={className} />
          </IconButton>
        );
      })}

      <MoreActions anchor={moreActionsOpen} close={closeMoreActions} />
    </Box>
  );
};

export default Actions;
