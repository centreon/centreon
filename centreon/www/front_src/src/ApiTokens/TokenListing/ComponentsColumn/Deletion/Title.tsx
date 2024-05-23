import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { labelDeleteToken } from '../../../translatedLabels';

import { useStyles } from './deletion.styles';

interface Props {
  title?: string;
}

const Title = ({ title = labelDeleteToken }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <Typography className={classes.title} variant="h6">
      {t(title)}
    </Typography>
  );
};

export default Title;
