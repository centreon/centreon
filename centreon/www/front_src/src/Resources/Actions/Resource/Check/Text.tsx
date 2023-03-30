import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { Typography } from '@mui/material';

const useStyles = makeStyles()((theme) => ({
  description: {
    fontSize: '0.8rem',
    whiteSpace: 'pre-line'
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

  const formatLabel = (label: string): string => {
    return label.charAt(0).toUpperCase() + label.slice(1).toLowerCase();
  };

  const formatDescription = (data: string): string => {
    const words = data.split(' ');

    const wordsWithLineBreak = words.map((word, index) => {
      return equals(index, Math.round(words.length / 2)) ? `${word}\n` : word;
    });

    return wordsWithLineBreak.join(' ');
  };

  return (
    <>
      <Typography className={classes.title}>{t(title)}</Typography>
      <Typography className={classes.description}>
        {formatDescription(t(description))}
      </Typography>
    </>
  );
};

export default Text;
