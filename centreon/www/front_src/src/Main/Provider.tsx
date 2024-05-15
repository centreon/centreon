import { lazy, useEffect } from 'react';

import { createBrowserRouter, RouterProvider } from 'react-router-dom';
import { not, startsWith, tail } from 'ramda';
import { createStore } from 'jotai';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';

import { Module } from '@centreon/ui';

import routeMap from '../reactRoutes/routeMap';
import AuthenticationDenied from '../FallbackPages/AuthenticationDenied';

const LoginPage = lazy(() => import('../Login'));
const ResetPasswordPage = lazy(() => import('../ResetPassword'));
const AppPage = lazy(() => import('./InitializationPage'));
const PublicPagesManager = lazy(
  () => import('../publicPages/PublicPagesManager')
);
const Main = lazy(() => import('.'));

export const store = createStore();

const Provider = (): JSX.Element | null => {
  const basename =
    (document
      .getElementsByTagName('base')[0]
      ?.getAttribute('href') as string) || '';

  const pathStartsWithBasename = startsWith(basename, window.location.pathname);

  useEffect(() => {
    if (pathStartsWithBasename) {
      return;
    }

    const path = tail(window.location.pathname);
    window.location.href = `${basename}${path}`;
  }, []);

  const router = createBrowserRouter(
    [
      {
        Component: Main,
        children: [
          {
            Component: AuthenticationDenied,
            path: routeMap.authenticationDenied
          },
          {
            Component: LoginPage,
            path: routeMap.login
          },
          {
            Component: ResetPasswordPage,
            path: routeMap.resetPassword
          },
          {
            Component: PublicPagesManager,
            path: routeMap.publicPages
          },
          {
            Component: AppPage,
            path: '*'
          }
        ],
        path: '/'
      }
    ],
    {
      basename
    }
  );

  if (not(pathStartsWithBasename)) {
    return null;
  }

  return (
    <Module maxSnackbars={2} seedName="centreon" store={store}>
      <>
        <RouterProvider router={router} />
        <ReactQueryDevtools />
      </>
    </Module>
  );
};

export default Provider;
