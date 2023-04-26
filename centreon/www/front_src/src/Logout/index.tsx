import { useEffect } from 'react';

import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';
import { useSetAtom } from 'jotai';

import { Box, LinearProgress, Typography } from '@mui/material';

import { CentreonLogo } from '@centreon/ui';
import { ThemeMode, userAtom } from '@centreon/ui-context';

import {
  hoveredNavigationItemsAtom,
  selectedNavigationItemsAtom
} from '../Navigation/Sidebar/sideBarAtoms';
import { passwordResetInformationsAtom } from '../ResetPassword/passwordResetInformationsAtom';
import { areUserParametersLoadedAtom } from '../Main/useUser';
import { logoutEndpoint } from '../api/endpoint';

import { labelYouWillBeDisconnected } from './translatedLabels';

const useStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'center',
    width: '100%'
  },
  logo: {
    display: 'flex',
    flexDirection: 'column',
    rowGap: theme.spacing(2),
    textAlign: 'center',
    width: '30%'
  }
}));

const Logout = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const setUser = useSetAtom(userAtom);
  const setHoveredNavigationItems = useSetAtom(hoveredNavigationItemsAtom);
  const setSelectedNavigationItems = useSetAtom(selectedNavigationItemsAtom);
  const setPasswordResetInformationsAtom = useSetAtom(
    passwordResetInformationsAtom
  );
  const setAreUserParametersLoaded = useSetAtom(areUserParametersLoadedAtom);

  useEffect(() => {
    setTimeout(() => {
      setAreUserParametersLoaded(false);
      setPasswordResetInformationsAtom(null);
      setSelectedNavigationItems(null);
      setHoveredNavigationItems(null);
      setUser((currentUser) => ({
        ...currentUser,
        themeMode: ThemeMode.light
      }));
      window.location.href = logoutEndpoint;
    }, 1000);
  }, []);

  return (
    <Box className={classes.container}>
      <div className={classes.logo}>
        <CentreonLogo />
        <Typography>{t(labelYouWillBeDisconnected)}</Typography>
        <LinearProgress color="primary" variant="indeterminate" />
      </div>
    </Box>
  );
};

export default Logout;
