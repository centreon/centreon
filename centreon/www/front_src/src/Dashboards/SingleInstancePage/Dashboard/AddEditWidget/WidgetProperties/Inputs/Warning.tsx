import { useTranslation } from 'react-i18next';

import { Box, Typography } from '@mui/material';

import { WidgetPropertyProps } from '../../models';
import { useWarningStyles } from './Inputs.styles';

const Warning = ({ label }: WidgetPropertyProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useWarningStyles();

  return (
    <Box className={classes.warningBox}>
      <Typography>{t(label)}</Typography>
    </Box>
  );
};

export default Warning;
