import { ComponentType, LazyExoticComponent, useMemo, useRef } from 'react';

import { useLocation } from 'react-router-dom';
import { fromPairs, replace } from 'ramda';

import { routes } from './routes';
import { ComponentProps, Parameters } from './models';

const getURLMatchRegExp = (url: string): RegExp => {
  const regexp = replace(/\[(\w+|\w+(-|_)\w+)\]/g, '(\\w+)', url);

  return new RegExp(`^${regexp}$`, 'g');
};

interface UsePageResolverState {
  matchedRoute?: [string, LazyExoticComponent<ComponentType<ComponentProps>>];
  parameters: Parameters;
}

const { log } = console;

export const usePageResolver = (): UsePageResolverState => {
  const { pathname } = usePageResolver.useLocation();
  const parametersNamesRef = useRef<Array<string>>([]);

  const formattedPathname = pathname.replace('/public', '');

  log('Coucou MedWass :)');

  const matchedRoute = useMemo(() => {
    log('useMemo', routes);

    return Object.entries(routes).find(([key]) => {
      log('use memo 2', key, key.matchAll(/\[(?<param>\w+|\w+(-|_)\w+)\]/g));

      parametersNamesRef.current = Array.from(
        key.matchAll(/\[(?<param>\w+|\w+(-|_)\w+)\]/g) || []
      ).map((match) => match[1]);

      return formattedPathname.match(getURLMatchRegExp(key));
    });
  }, [formattedPathname]);

  const parameterValues = matchedRoute
    ? Array.from(
        formattedPathname.matchAll(getURLMatchRegExp(matchedRoute[0])) || []
      )[0]
        .slice(1)
        .filter((v) => v)
    : [];

  const parameters = fromPairs(
    parameterValues.map((value, index) => [
      parametersNamesRef.current[index],
      value
    ])
  );

  return {
    matchedRoute,
    parameters
  };
};

usePageResolver.useLocation = useLocation;
