import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';

import { IconButton, Image, LoadingSkeleton, ImageVariant } from '@centreon/ui';

import { labelCentreonLogo } from '../../../translatedLabels';

import miniLogoLight from './Centreon_Logo_Blanc_C.svg';

interface Props {
  onClick: () => void;
}
const useStyles = makeStyles()((theme) => ({
  miniLogo: {
    height: theme.spacing(5),
    width: theme.spacing(3)
  }
}));

const MiniLogo = ({ onClick }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const label = t(labelCentreonLogo);

  return (
    <IconButton ariaLabel={label} title={label} onClick={onClick}>
      <Image
        alt={label}
        className={classes.miniLogo}
        fallback={
          <LoadingSkeleton className={classes.miniLogo} variant="circular" />
        }
        imagePath={miniLogoLight}
        variant={ImageVariant.Contain}
      />
    </IconButton>
  );
};

export default MiniLogo;
