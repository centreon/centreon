import { useAtom } from 'jotai';
import { gt } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box, Button } from '@mui/material';

import { PanelSize, togglePanelSize } from '@centreon/ui';

import { panelWidthStorageAtom } from '../atom';
import {
  labelExpandInformationPanel,
  labelReduceInformationPanel
} from '../translatedLabels';

import useStyles from './Form/Form.styles';

const ReducePanelButton = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const [panelWidth, setPanelWidth] = useAtom(panelWidthStorageAtom);

  const handlePanelWidth = (): void => {
    setPanelWidth((prevState) => togglePanelSize({ currentSize: prevState }));
  };

  const panelWidthLabel = gt(panelWidth, PanelSize.Medium)
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

export default ReducePanelButton;
