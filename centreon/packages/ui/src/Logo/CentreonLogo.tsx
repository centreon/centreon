import { FC } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Image, ImageVariant } from '..';
import LoadingSkeleton from '../LoadingSkeleton';
import { useThemeMode } from '../utils/useThemeMode';
import CentreonLogoLight from '../../assets/centreon-logo-one-line-light.svg';
import CentreonLogoDark from '../../assets/centreon-logo-one-line-dark.svg';

import { labelCentreonLogo } from './translatedLabels';

const useStyles = makeStyles()((theme) => ({
  logo: {
    maxHeight: theme.spacing(7),
    maxWidth: theme.spacing(30)
  }
}));

export const CentreonLogo: FC = () => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { isDarkMode } = useThemeMode();

  const logo = isDarkMode ? CentreonLogoDark : CentreonLogoLight;

  return (
    <Image
      alt={t(labelCentreonLogo)}
      className={classes.logo}
      fallback={
        <LoadingSkeleton className={classes.logo} height="100%" width="100%" />
      }
      imagePath={logo}
      variant={ImageVariant.Contain}
    />
  );
};
