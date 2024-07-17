import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { useTextStyles } from './check.styles';

interface Props {
  description: string;
  title: string;
}

const Text = ({ title, description }: Props): JSX.Element => {
  const { classes } = useTextStyles();
  const { t } = useTranslation();

  return (
    <>
      <Typography className={classes.title}>{t(title)}</Typography>
      <Typography className={classes.description}>{t(description)}</Typography>
    </>
  );
};

export default Text;
