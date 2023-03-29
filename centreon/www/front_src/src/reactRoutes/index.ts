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
  }
];

export default reactRoutes;
