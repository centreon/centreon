import { FC } from 'react';

import { useTranslation } from 'react-i18next';

import { Image, ImageVariant } from '..';
import LoadingSkeleton from '../LoadingSkeleton';
import { useThemeMode } from '../utils/useThemeMode';
import CentreonLogoLight from '../../assets/centreon-logo-light.svg';
import CentreonLogoDark from '../../assets/centreon-logo-dark.svg';

import { labelCentreonLogo } from './translatedLabels';

export const CentreonLogo: FC = () => {
  const { t } = useTranslation();
  const { isDarkMode } = useThemeMode();

  const logo = isDarkMode ? CentreonLogoDark : CentreonLogoLight;

  return (
    <Image
      alt={t(labelCentreonLogo)}
      fallback={<LoadingSkeleton />}
      imagePath={logo}
      variant={ImageVariant.Contain}
    />
  );
};
