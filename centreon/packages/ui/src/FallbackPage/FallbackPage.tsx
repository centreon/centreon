import { FC } from 'react';

import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Typography } from '@mui/material';

import { Image } from '..';
import NotAuthorizedTemplateBackgroundDark from '../@assets/images/not-authorized-template-background-dark.svg';
import NotAuthorizedTemplateBackgroundLight from '../@assets/images/not-authorized-template-background-light.svg';
import LoadingSkeleton from '../LoadingSkeleton';
import { CentreonLogo } from '../Logo/CentreonLogo';
import { typedMemo } from '../utils/typedMemo';
import { useThemeMode } from '../utils/useThemeMode';

const useStyles = makeStyles()((theme) => ({
  logo: {
    alignSelf: 'flex-end',
    height: theme.spacing(11),
    width: '239px'
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
        <div className={classes.logo}>
          <CentreonLogo />
        </div>
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
