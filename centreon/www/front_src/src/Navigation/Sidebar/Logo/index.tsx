import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';

import { IconButton, Image, ImageVariant, LoadingSkeleton } from '@centreon/ui';

import { labelCentreonLogo, labelMiniCentreonLogo } from '../../translatedLabels';

import centreonLogoWhite from '../../../assets/centreon-logo-white.svg';
import centreonLogoWhiteMini from '../../../assets/centreon-logo-white-mini.svg';

interface Props {
  onClick: () => void;
  isMiniLogo: boolean;
}
const useStyles = makeStyles()((theme) => ({
  logo: {
    height: theme.spacing(4.2),
    maxWidth: theme.spacing(16.9)
  }
}));

const Logo = ({ onClick, isMiniLogo }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const label = t(isMiniLogo ? labelMiniCentreonLogo : labelCentreonLogo);

  return (
    <div aria-label={label} title={label} onClick={onClick}>
      <Image
        alt={label}
        className={classes.logo}
        fallback={
          <LoadingSkeleton className={classes.logo} variant="circular" />
        }
        imagePath={centreonLogoWhite}
        variant={ImageVariant.Contain}
      />
    </div>
  );
};

export default Logo;
