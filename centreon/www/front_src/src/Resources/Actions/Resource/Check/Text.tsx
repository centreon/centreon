import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

const useStyles = makeStyles()((theme) => ({
  description: {
    fontSize: '0.8rem',
    maxWidth: theme.spacing(31)
  },
  title: {
    color: theme.palette.info.main,
    fontWeight: 'bold'
  }
}));

interface Props {
  description: string;
  title: string;
}

const Text = ({ title, description }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <>
      <Typography className={classes.title}>{t(title)}</Typography>
      <Typography className={classes.description}>{t(description)}</Typography>
    </>
  );
};

export default Text;
