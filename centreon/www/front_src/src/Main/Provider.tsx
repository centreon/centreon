import { lazy, useEffect } from 'react';

import { Route, RouterProvider, Routes, createBrowserRouter, createRoutesFromElements } from 'react-router-dom';
import { not, startsWith, tail } from 'ramda';
import { createStore } from 'jotai';

import { Module, QueryProvider } from '@centreon/ui';
import routeMap from '../reactRoutes/routeMap';
import AuthenticationDenied from '../FallbackPages/AuthenticationDenied';

const LoginPage = lazy(() => import('../Login'));
const ResetPasswordPage = lazy(() => import('../ResetPassword'));
const AppPage = lazy(() => import('./InitializationPage'));
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

  const router = createBrowserRouter([
    {
      path: '/',
      Component: Main,
      children: [
        {
          path: routeMap.authenticationDenied,
          Component: AuthenticationDenied,
        }, {
          path: routeMap.login,
          Component: LoginPage,
        },
        {
          path: routeMap.resetPassword,
          Component: ResetPasswordPage,
        },
        {
          path: '*',
          Component: AppPage,
        }
      ]
    }
  ], {
    basename
  })

  if (not(pathStartsWithBasename)) {
    return null;
  }

  return (
    <Module maxSnackbars={2} seedName="centreon" store={store}>
      <QueryProvider>
        <RouterProvider router={router} />
      </QueryProvider>
    </Module>
  );
};

export default Provider;
