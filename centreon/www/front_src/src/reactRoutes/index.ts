import { lazy } from 'react';

import routeMap from './routeMap';

const reactRoutes = [
  {
    comp: lazy(() => import('../route-components/pollerWizard')),
    path: routeMap.pollerWizard
  },
  {
    comp: lazy(() => import('../Extensions')),
    path: routeMap.extensionsManagerPage
  },
  {
    comp: lazy(() => import('../FallbackPages/NotAllowedPage')),
    path: routeMap.notAllowedPage
  },
  {
    comp: lazy(() => import('../Resources')),
    path: routeMap.resources
  },
  {
    comp: lazy(() => import('../Authentication')),
    path: routeMap.authentication
  },
  {
    comp: lazy(() => import('../ResetPassword')),
    path: routeMap.resetPassword
  },
  {
    comp: lazy(() => import('../Logout')),
    path: routeMap.logout
  },
  {
    comp: lazy(() => import('../About/About')),
    path: routeMap.about
  },
  {
    comp: lazy(() => import('../CloudNotificationsConfiguration')),
    path: routeMap.cloudNotificationConfiguration
  },
  {
    comp: lazy(() => import('../Dashboards')),
    path: routeMap.dashboards
  },
  {
    comp: lazy(() => import('../Dashboards/SingleInstancePage/Pages')),
    path: routeMap.dashboard
  },
  {
    comp: lazy(() => import('../AuthenticationTokens')),
    path: routeMap.authTokens
  },
  {
    comp: lazy(() => import('../ResourceAccessManagement')),
    path: routeMap.resourceAccessManagement
  },
  {
    comp: lazy(() => import('../Configuration/AdditionnalConnectors')),
    path: routeMap.additionalConnectorConfiguration
  },
  {
    comp: lazy(() => import('../VaultConfiguration/VaultConfiguration')),
    path: routeMap.vaultConfiguration
  },
  {
    comp: lazy(() => import('../AgentConfiguration/Page')),
    path: routeMap.agentConfigurations
  },
  {
    comp: lazy(() => import('../Configuration/HostGroups')),
    path: routeMap.hostGroups
  }
];

export default reactRoutes;
