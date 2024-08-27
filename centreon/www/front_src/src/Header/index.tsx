import { useRef } from 'react';

import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Theme } from '@mui/material';

import { useFullscreen } from '@centreon/ui';
import { ThemeMode } from '@centreon/ui-context';

import FederatedComponent from '../components/FederatedComponents';

import Poller from './Poller';
import HostStatusCounter from './Resources/Host';
import ServiceStatusCounter from './Resources/Service';
import UserMenu from './UserMenu';

export const isDarkMode = (theme: Theme): boolean =>
  equals(theme.palette.mode, ThemeMode.dark);

export const headerHeight = 7;

const useStyles = makeStyles()((theme) => ({
  fullscreenActivated: {
    display: 'none'
  },
  header: {
    alignItems: 'center',
    backgroundColor: isDarkMode(theme)
      ? theme.palette.common.black
      : theme.palette.primary.dark,
    display: 'flex',
    maxHeight: theme.spacing(headerHeight),
    minHeight: theme.spacing(headerHeight),
    padding: `${theme.spacing(1)} ${theme.spacing(3)}`,
    zIndex: theme.zIndex.drawer
  },
  item: {
    '&:empty, &:last-of-type': {
      marginRight: 0
    },
    '&:first-of-type': {
      borderRight: `solid 1px ${theme.palette.common.white}`,
      marginRight: theme.spacing(3.5),
      paddingRight: theme.spacing(3.5),

      [theme.breakpoints.down(960)]: {
        marginRight: theme.spacing(2.5),
        paddingRight: theme.spacing(2.5)
      }
    },
    flex: 'initial',
    marginRight: theme.spacing(6),

    [theme.breakpoints.down(960)]: {
      marginRight: theme.spacing(3.5)
    }
  },
  leftContainer: {
    alignItems: 'center',
    display: 'flex'
  },
  rigthContainer: {
    alignItems: 'center',
    display: 'flex',
    marginLeft: 'auto'
  }
}));

const Header = (): JSX.Element => {
  const { classes, cx } = useStyles();
  const headerRef = useRef<HTMLElement>(null);

  const { isFullscreenActivated } = useFullscreen();

  return (
    <header
      className={cx(
        classes.header,
        isFullscreenActivated && classes.fullscreenActivated
      )}
      ref={headerRef}
    >
      <div className={classes.leftContainer}>
        <div className={classes.item}>
          <Poller />
        </div>

        <div className={classes.item}>
          <ServiceStatusCounter />
        </div>

        <div className={classes.item}>
          <HostStatusCounter />
        </div>

        <div className={classes.item}>
          <FederatedComponent path="/bam/header/topCounter" />
        </div>
      </div>

      <div className={classes.rigthContainer}>
        <div className={classes.platformName}>
          <FederatedComponent path="/it-edition-extensions/header/platformName" />
        </div>
        <UserMenu headerRef={headerRef} />
      </div>
    </header>
  );
};

export default Header;
