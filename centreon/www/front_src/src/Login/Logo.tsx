import { useAtomValue } from 'jotai/utils';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { useMemoComponent } from '@centreon/ui';
import { ThemeMode, userAtom } from '@centreon/ui-context';

import logoCentreon from '../assets/logo-centreon-colors.png';
import logoWhite from '../assets/centreon-logo-white.svg';

import { labelCentreonLogo } from './translatedLabels';

const useStyles = makeStyles()({
  centreonLogo: {
    height: '100%',
    objectFit: 'contain',
    width: '100%'
  },
  centreonLogoWhite: {
    height: 57,
    width: 250
  }
});

const Logo = (): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();
  const { themeMode } = useAtomValue(userAtom);
  const logo = equals(themeMode, ThemeMode.light) ? logoCentreon : logoWhite;
  const isDarkMode = equals(themeMode, ThemeMode.dark);

  return useMemoComponent({
    Component: (
      <img
        alt={t(labelCentreonLogo)}
        aria-label={t(labelCentreonLogo)}
        className={cx(classes.centreonLogo, {
          [classes.centreonLogoWhite]: isDarkMode
        })}
        src={logo}
      />
    ),
    memoProps: [isDarkMode]
  });
};

export default Logo;
