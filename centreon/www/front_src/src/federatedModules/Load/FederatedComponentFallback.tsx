import { useTranslation } from 'react-i18next';

import VisibilityOffIcon from '@mui/icons-material/VisibilityOff';
import { Paper, Typography } from '@mui/material';

import { labelCannotLoadModule } from '../translatedLabels';

import useStyles from './useStyles';

const FederatedComponentFallback = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <Paper className={classes.moduleContainer}>
      <VisibilityOffIcon fontSize="small" />
      <Typography variant="body2">{t(labelCannotLoadModule)}</Typography>
    </Paper>
  );
};

export default FederatedComponentFallback;
