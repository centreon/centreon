import { LazyExoticComponent, useMemo } from 'react';

import { useLocation } from 'react-router-dom';

import { routes } from './routes';

export const usePageResolver = ():
  | [string, LazyExoticComponent<() => JSX.Element>]
  | undefined => {
  const { pathname } = usePageResolver.useLocation();

  const formattedPathname = pathname.replace('/public', '');

  const matchedRoute = useMemo(
    () =>
      Object.entries(routes).find(([key]) => {
        const regexp = key.replace(/\[\S+\]/, '\\w+');

        return formattedPathname.match(new RegExp(`^${regexp}$`));
      }),
    [formattedPathname]
  );

  return matchedRoute;
};

usePageResolver.useLocation = useLocation;
