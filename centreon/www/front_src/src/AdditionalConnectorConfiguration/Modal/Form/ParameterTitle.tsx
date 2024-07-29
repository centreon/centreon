import { useTranslation } from 'react-i18next';

import HelpIcon from '@mui/icons-material/HelpOutline';
import { Box, Typography } from '@mui/material';

import { Tooltip } from '@centreon/ui/components';

import { useFormStyles } from '../useModalStyles';
import {
  labelParameters,
  labelParametersTooltip
} from '../../translatedLabels';

const ParameterTitle = (): JSX.Element => {
  const { classes } = useFormStyles();
  const { t } = useTranslation();

  return (
    <Box className={classes.parametersTitle}>
      <Typography className={classes.parametersTitleText}>
        {t(labelParameters)}
      </Typography>
      <Tooltip
        className={classes.parametersTitleTooltip}
        label={t(labelParametersTooltip)}
      >
        <HelpIcon />
      </Tooltip>
    </Box>
  );
};

export default ParameterTitle;
