import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';

import { IconButton, Image, ImageVariant, LoadingSkeleton } from '@centreon/ui';

import { labelCentreonLogo } from '../../translatedLabels';

import logoLight from './Centreon_Logo_Blanc.svg';

interface Props {
  onClick: () => void;
}
const useStyles = makeStyles()((theme) => ({
  logo: {
    height: theme.spacing(5),
    width: theme.spacing(16.9)
  }
}));

const Logo = ({ onClick }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const label = t(labelCentreonLogo);

  return (
    <IconButton ariaLabel={label} title={label} onClick={onClick}>
      <Image
        alt={label}
        className={classes.logo}
        fallback={
          <LoadingSkeleton className={classes.logo} variant="circular" />
        }
        imagePath={logoLight}
        variant={ImageVariant.Contain}
      />
    </IconButton>
  );
};

export default Logo;
