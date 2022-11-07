<<<<<<< HEAD
import { makeStyles } from '@mui/styles';

import Hook from '../components/Hook';

import PollerMenu from './PollerMenu';
import HostStatusCounter from './RessourceStatusCounter/Host';
import ServiceStatusCounter from './RessourceStatusCounter/Service';
import UserMenu from './userMenu';
import SwitchMode from './SwitchThemeMode';

const HookComponent = Hook as unknown as (props) => JSX.Element;

const useStyles = makeStyles((theme) => ({
  container: {
    alignItems: 'center',
    display: 'flex',
    justifyContent: 'space-between',
  },
  header: {
    background: theme.palette.common.black,
  },
  rightContainer: {
    display: 'flex',
  },
}));

const Header = (): JSX.Element => {
  const classes = useStyles();

  return (
    <header className={classes.header}>
      <div className={classes.container}>
        <div>
          <PollerMenu />
        </div>
        <div className={classes.rightContainer}>
          <HookComponent path="/header/topCounter" />
          <HostStatusCounter />
          <ServiceStatusCounter />
          <SwitchMode />
=======
import React from 'react';

import classnames from 'classnames';

import { useUserContext } from '@centreon/ui-context';

import Hook from '../components/Hook';

import styles from './header.scss';
import PollerMenu from './pollerMenu';
import UserMenu from './userMenu';
import ServiceStatusCounter from './RessourceStatusCounter/Service';
import HostStatusCounter from './RessourceStatusCounter/Host';

const HookComponent = Hook as unknown as (props) => JSX.Element;

const Header = (): JSX.Element => {
  const { refreshInterval } = useUserContext();

  return (
    <header className={styles.header}>
      <div className={styles['header-icons']}>
        <div className={classnames(styles.wrap, styles['wrap-left'])}>
          <PollerMenu refreshInterval={refreshInterval} />
        </div>
        <div className={classnames(styles.wrap, styles['wrap-right'])}>
          <HookComponent path="/header/topCounter" />
          <HostStatusCounter />
          <ServiceStatusCounter />
>>>>>>> centreon/dev-21.10.x
          <UserMenu />
        </div>
      </div>
    </header>
  );
};

export default Header;
