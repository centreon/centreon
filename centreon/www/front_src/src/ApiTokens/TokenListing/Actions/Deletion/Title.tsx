import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { labelDeleteToken } from '../../../translatedLabels';

import { useStyles } from './deletion.styles';

const Title = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <Typography className={classes.title} variant="h6">
      {t(labelDeleteToken)}
    </Typography>
  );
};

export default Title;
