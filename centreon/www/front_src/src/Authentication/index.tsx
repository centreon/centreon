import { useEffect, useMemo, useRef, useState } from 'react';

import { useTranslation } from 'react-i18next';
import { useAtomValue, useSetAtom } from 'jotai';
import { makeStyles } from 'tss-react/mui';

import { Box, Container, Paper, Tab } from '@mui/material';
import { TabContext, TabList, TabPanel } from '@mui/lab';

import { userAtom } from '@centreon/ui-context';

import { Provider } from './models';
import LocalAuthentication from './Local';
import { labelPasswordSecurityPolicy } from './Local/translatedLabels';
import { labelOpenIDConnectConfiguration } from './Openid/translatedLabels';
import OpenidConfiguration from './Openid';
import WebSSOConfigurationForm from './WebSSO';
import SAMLConfigurationForm from './SAML';
import { labelWebSSOConfiguration } from './WebSSO/translatedLabels';
import { tabAtom, appliedTabAtom } from './tabAtoms';
import passwordPadlockLogo from './logos/passwordPadlock.svg';
import providerPadlockLogo from './logos/providerPadlock.svg';
import { labelSAMLConfiguration } from './SAML/translatedLabels';

const panels = [
  {
    Component: LocalAuthentication,
    image: passwordPadlockLogo,
    title: labelPasswordSecurityPolicy,
    value: Provider.Local
  },
  {
    Component: OpenidConfiguration,
    image: providerPadlockLogo,
    title: labelOpenIDConnectConfiguration,
    value: Provider.Openid
  },
  {
    Component: WebSSOConfigurationForm,
    image: providerPadlockLogo,
    title: labelWebSSOConfiguration,
    value: Provider.WebSSO
  },
  {
    Component: SAMLConfigurationForm,
    image: providerPadlockLogo,
    title: labelSAMLConfiguration,
    value: Provider.SAML
  }
];

const useStyles = makeStyles()((theme) => ({
  box: {
    overflowY: 'auto'
  },
  container: {
    marginLeft: '0',
    maxHeight: `calc(100vh - ${theme.spacing(12)})`
  },
  formContainer: {
    display: 'grid',
    gridTemplateColumns: '1.2fr 0.6fr',
    padding: theme.spacing(3)
  },
  image: {
    height: '300px',
    opacity: 0.5,
    padding: theme.spacing(0, 5),
    position: 'sticky',
    top: 0,
    width: '300px'
  },
  panel: {
    padding: 0
  },
  paper: {
    border: 'none'
  },
  tabList: {
    borderBottom: `${theme.spacing(0.25)} solid ${theme.palette.divider}`
  }
}));

const scrollMargin = 8;

const Authentication = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const formContainerRef = useRef<HTMLDivElement | null>(null);

  const [windowHeight, setWindowHeight] = useState(window.innerHeight);
  const [clientRect, setClientRect] = useState<DOMRect | null>(null);

  const appliedTab = useAtomValue(appliedTabAtom);
  const { themeMode } = useAtomValue(userAtom);
  const setTab = useSetAtom(tabAtom);

  const changeTab = (_, newTab: Provider): void => {
    setTab(newTab);
  };

  const resize = (): void => {
    setWindowHeight(window.innerHeight);
  };

  useEffect(() => {
    window.addEventListener('resize', resize);

    setClientRect(formContainerRef.current?.getBoundingClientRect() ?? null);

    return () => {
      window.removeEventListener('resize', resize);
    };
  }, []);

  const formContainerHeight =
    windowHeight - (clientRect?.top || 0) - scrollMargin;

  const tabs = useMemo(
    () =>
      panels.map(({ title, value }) => (
        <Tab aria-label={t(title)} key={value} label={t(title)} value={value} />
      )),
    []
  );

  const tabPanels = useMemo(
    () =>
      panels.map(({ Component, value, image }) => (
        <TabPanel className={classes.panel} key={value} value={value}>
          <Box
            ref={formContainerRef}
            sx={{ height: `${formContainerHeight}px` }}
          >
            <div className={classes.formContainer}>
              <Component />
              <img alt="padlock" className={classes.image} src={image} />
            </div>
          </Box>
        </TabPanel>
      )),
    [themeMode, formContainerHeight]
  );

  return (
    <Box className={classes.box}>
      <TabContext value={appliedTab}>
        <Container className={classes.container}>
          <Paper square className={classes.paper}>
            <TabList
              className={classes.tabList}
              variant="fullWidth"
              onChange={changeTab}
            >
              {tabs}
            </TabList>
            {tabPanels}
          </Paper>
        </Container>
      </TabContext>
    </Box>
  );
};

export default Authentication;
