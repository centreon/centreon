import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { Box } from '@mui/material';

import { panelModeAtom } from '../atom';
import { PanelMode } from '../models';

import useStyles from './Header.styles';
import NotificationName from './NotificationName';
import {
  DeleteAction,
  DuplicateAction,
  ActivateAction,
  ClosePanelAction,
  SaveAction
} from './Actions';

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
