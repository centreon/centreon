import { useAtom, useSetAtom } from 'jotai';
import { makeStyles } from 'tss-react/mui';

import { Box } from '@mui/material';

import { Panel } from '@centreon/ui';

import { isPanelOpenAtom, panelWidthStorageAtom } from '../atom';

import { Header } from './Header';

const useStyle = makeStyles()((theme) => ({
  pannel: {
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
    <Box className={classes.pannel}>
      <Panel
        header={<Header />}
        selectedTab={<div />}
        width={panelWidth}
        onClose={handleClose}
        onResize={setPanelWidth}
      />
    </Box>
  );
};

export default EditPanel;
