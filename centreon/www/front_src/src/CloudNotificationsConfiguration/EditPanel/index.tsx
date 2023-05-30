import { equals } from 'ramda';
import { useAtom, useSetAtom } from 'jotai';
import { makeStyles } from 'tss-react/mui';

import { Box } from '@mui/material';

import { Panel } from '@centreon/ui';
import { ThemeMode } from '@centreon/ui-context';

import { isPanelOpenAtom, panelWidthStorageAtom } from '../atom';

import Form from './Form';

const useStyle = makeStyles()((theme) => ({
  panel: {
    backgroundColor: equals(theme.palette.mode, ThemeMode.dark)
      ? theme.palette.common.black
      : theme.palette.background.panel
  },
  panelContainer: {
    display: 'flex',
    flexDirection: 'row-reverse',
    height: `calc(100vh - ${theme.spacing(20)})`
  }
}));

const EditPanel = (): JSX.Element => {
  const { classes } = useStyle();
  const [panelWidth, setPanelWidth] = useAtom(panelWidthStorageAtom);
  const setIsPanelOpen = useSetAtom(isPanelOpenAtom);

  const handleClose = (): void => setIsPanelOpen(false);

  return (
    <Box className={classes.panelContainer}>
      <Panel
        className={classes.panel}
        selectedTab={<Form />}
        width={panelWidth}
        onClose={handleClose}
        onResize={setPanelWidth}
      />
    </Box>
  );
};

export default EditPanel;
