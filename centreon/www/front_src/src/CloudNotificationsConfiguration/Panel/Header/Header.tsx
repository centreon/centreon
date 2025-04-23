import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { Box } from '@mui/material';

import { panelModeAtom } from '../atom';
import { PanelMode } from '../models';

import {
  ActivateAction,
  ClosePanelAction,
  DeleteAction,
  DuplicateAction,
  SaveAction
} from './Actions';
import useStyles from './Header.styles';
import NotificationName from './NotificationName';

const Header = (): JSX.Element => {
  const { classes } = useStyles();

  const panelMode = useAtomValue(panelModeAtom);

  return (
    <Box className={classes.panelHeader}>
      <NotificationName />
      <Box className={classes.rightHeader}>
        <Box className={classes.actions}>
          <ActivateAction />
          {equals(panelMode, PanelMode.Edit) && <DuplicateAction />}
          <SaveAction />
          {equals(panelMode, PanelMode.Edit) && <DeleteAction />}
        </Box>
        <ClosePanelAction />
      </Box>
    </Box>
  );
};

export default Header;
