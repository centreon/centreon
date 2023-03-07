import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Typography } from '@mui/material';

import { Image, LoadingSkeleton } from '@centreon/ui';

import logoCentreon from '../assets/logo-centreon-colors.svg';
import { labelCentreonLogo } from '../Login/translatedLabels';

import { labelCentreonIsLoading } from './translatedLabels';

const useStyles = makeStyles()((theme) => ({
  loader: {
    alignItems: 'center',
    backgroundColor: theme.palette.background.paper,
    display: 'flex',
    flexDirection: 'column',
    height: '100vh',
    justifyContent: 'center',
    rowGap: theme.spacing(2),
    width: '100%'
  },
  logo: {
    width: '30%'
  }
}));

export const MainLoader = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const label = t(labelCentreonLogo);

  return (
    <div className={classes.loader}>
      <Image alt={label} imagePath={logoCentreon} className={classes.logo} fallback={<LoadingSkeleton className={classes.logo} />} />
      <Typography>{t(labelCentreonIsLoading)}</Typography>
    </div>
  );
};

export const MainLoaderWithoutTranslation = (): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.loader}>
      <Image alt={labelCentreonLogo} imagePath={logoCentreon} className={classes.logo} fallback={<LoadingSkeleton className={classes.logo} />} />
      <Typography>{labelCentreonIsLoading}</Typography>
    </div>
  );
};
