import { useAtom, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Box } from '@mui/material';

import { Panel as PanelComponent } from '@centreon/ui';
import { ThemeMode } from '@centreon/ui-context';

import { isPanelOpenAtom, panelWidthStorageAtom } from '../atom';

import Form from './Form/Form';

interface Props {
  marginBottom?: number;
}

const useStyle = makeStyles<Required<Props>>()((theme, { marginBottom }) => ({
  panel: {
    backgroundColor: equals(theme.palette.mode, ThemeMode.dark)
      ? theme.palette.common.black
      : theme.palette.background.panel
  },
  panelContainer: {
    display: 'flex',
    flexDirection: 'row-reverse',
    height: `calc(100vh - ${theme.spacing(marginBottom)})`
  }
}));

const Panel = ({ marginBottom = 20 }: Props): JSX.Element => {
  const { classes } = useStyle({ marginBottom });
  const [panelWidth, setPanelWidth] = useAtom(panelWidthStorageAtom);
  const setIsPanelOpen = useSetAtom(isPanelOpenAtom);

  const handleClose = (): void => setIsPanelOpen(false);

  return (
    <Box className={classes.panelContainer}>
      <PanelComponent
        className={classes.panel}
        selectedTab={<Form />}
        width={panelWidth}
        onClose={handleClose}
        onResize={setPanelWidth}
      />
    </Box>
  );
};

export default Panel;
