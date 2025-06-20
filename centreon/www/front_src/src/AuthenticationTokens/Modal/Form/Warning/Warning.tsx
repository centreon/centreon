import { Box, Typography } from '@mui/material';
import { useTranslation } from 'react-i18next';
import { labelSecurityToken } from '../../../translatedLabels';
import { useStyles } from './Warning.styles';

const TokenCopyWarning = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  return (
    <Box className={classes.warningBox}>
      <Typography>{t(labelSecurityToken)}</Typography>
    </Box>
  );
};

export default TokenCopyWarning;
