import { FC } from 'react';

import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Typography } from '@mui/material';

import LoadingSkeleton from '../LoadingSkeleton';
import { CentreonLogo } from '../Logo/CentreonLogo';
import NotAuthorizedTemplateBackgroundDark from '../../assets/not-authorized-template-background-dark.svg';
import NotAuthorizedTemplateBackgroundLight from '../../assets/not-authorized-template-background-light.svg';
import { Image } from '..';
import { useThemeMode } from '../utils/useThemeMode';
import { typedMemo } from '../utils/typedMemo';

const useStyles = makeStyles()((theme) => ({
  logo: {
    alignSelf: 'flex-end',
    height: theme.spacing(11),
    width: '22rem'
  },
  message: {
    color: theme.palette.text.primary
  },
  messageBlock: {
    alignContent: 'center',
    alignSelf: 'flex-start',
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(3),
    justifyContent: 'center',
    maxWidth: '40%',
    overflow: 'visible',
    textAlign: 'center'
  },
  notAuthorizedContainer: {
    alignItems: 'center',
    display: 'grid',
    gridTemplateRows: '2fr 3fr 2fr',
    height: '100%',
    justifyItems: 'center',
    position: 'relative',
    width: '100%'
  },
  wallpaper: {
    height: '100%',
    position: 'absolute',
    width: '100%'
  }
}));

interface FallbackPageProps {
  contactAdmin?: string;
  message: string;
  title: string;
}

export const FallbackPage: FC<FallbackPageProps> = typedMemo(
  ({ title, message, contactAdmin }) => {
    const { classes } = useStyles();
    const { isDarkMode } = useThemeMode();

    const wallpaper = isDarkMode
      ? NotAuthorizedTemplateBackgroundDark
      : NotAuthorizedTemplateBackgroundLight;

    return (
      <div className={classes.notAuthorizedContainer}>
        <section className={classes.logo}>
          <CentreonLogo />
        </section>
        <section className={classes.messageBlock}>
          <header>
            <Typography color="primary" fontWeight="bold" variant="h3">
              {title}
            </Typography>
          </header>
          <div>
            <Typography className={classes.message} variant="h5">
              {message}
            </Typography>
            {contactAdmin && (
              <Typography className={classes.message} variant="h6">
                {contactAdmin}
              </Typography>
            )}
          </div>
        </section>
        <Image
          alt={message}
          className={classes.wallpaper}
          fallback={<LoadingSkeleton className={classes.wallpaper} />}
          imagePath={wallpaper}
        />
      </div>
    );
  },
  equals
);

FallbackPage.displayName = 'FallbackPage';
