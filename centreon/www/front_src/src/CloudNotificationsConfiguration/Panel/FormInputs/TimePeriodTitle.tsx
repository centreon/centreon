import { useTranslation } from 'react-i18next';

import HelpIcon from '@mui/icons-material/HelpOutline';
import { Box, Tooltip } from '@mui/material';

import {
  labelTimePeridoTooltip,
  labelTimePeriod
} from '../../translatedLabels';

import { useStyles } from './Inputs.styles';

const TimePeriodTitle = (): JSX.Element => {
  const { classes } = useStyles({});
  const { t } = useTranslation();

  return (
    <Box className={classes.timeperiod}>
      <Box>{t(labelTimePeriod)}</Box>
      <Tooltip
        className={classes.timeperiodTooltip}
        title={t(labelTimePeridoTooltip)}
      >
        <HelpIcon />
      </Tooltip>
    </Box>
  );
};

export default TimePeriodTitle;
