import { useTranslation } from 'react-i18next';
import { useAtom } from 'jotai';
import { gt } from 'ramda';

import { Box, Button } from '@mui/material';

import {
  labelExpandInformationPanel,
  labelReduceInformationPanel
} from '../translatedLabels';
import { panelWidthStorageAtom } from '../atom';

import useStyles from './Form.styles';

const ReducePanel = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const [panelWidth, setPanelWidth] = useAtom(panelWidthStorageAtom);

  const handlePanelWidth = (): void => {
    setPanelWidth((prevState) => (gt(prevState, 675) ? 550 : 800));
  };

  const panelWidthLabel = gt(panelWidth, 675)
    ? t(labelReduceInformationPanel)
    : t(labelExpandInformationPanel);

  return (
    <Box className={classes.reducePanel}>
      <Button
        className={classes.reducePanelButton}
        data-testid={labelReduceInformationPanel}
        onClick={handlePanelWidth}
      >
        {panelWidthLabel}
      </Button>
    </Box>
  );
};

export default ReducePanel;
