import { IconButton, Image, ImageVariant, LoadingSkeleton } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import centreonLogoWhite from '../../assets/centreon-logo-white.svg';
import centreonLogoWhiteMini from '../../assets/centreon-logo-white-mini.svg';
import { labelCentreonLogo, labelMiniCentreonLogo } from '../translatedLabels';

interface Props {
  isMiniLogo: boolean;
  onClick: () => void;
}

const logoClassName = 'h-8.5 max-w-34';

const Logo = ({ onClick, isMiniLogo }: Props): JSX.Element => {
  const { t } = useTranslation();

  const label = t(isMiniLogo ? labelMiniCentreonLogo : labelCentreonLogo);

  return (
    <IconButton ariaLabel={label} onClick={onClick} title={label}>
      <Image
        alt={label}
        className={logoClassName}
        fallback={
          <LoadingSkeleton className={logoClassName} variant="circular" />
        }
        imagePath={isMiniLogo ? centreonLogoWhiteMini : centreonLogoWhite}
        variant={ImageVariant.Contain}
      />
    </IconButton>
  );
};

export default Logo;
