import { ComponentType, LazyExoticComponent, useMemo, useRef } from 'react';

import { useLocation } from 'react-router-dom';
import { fromPairs, replace } from 'ramda';

import { routes } from './routes';

const getURLMatchRegExp = (url: string): RegExp => {
  const regexp = replace(/\[(\w+|\w+(-|_)\w+)\]/g, '(\\w+)', url);

  return new RegExp(`^${regexp}$`, 'g');
};

interface UsePageResolverState {
  matchedRoute?: [string, LazyExoticComponent<ComponentType>];
  parameters: {
    [x: string]: unknown;
    [x: number]: unknown;
    [x: symbol]: unknown;
  };
}

export const usePageResolver = (): UsePageResolverState => {
  const { pathname } = usePageResolver.useLocation();
  const parametersNamesRef = useRef([]);

  const formattedPathname = pathname.replace('/public', '');

  const matchedRoute = useMemo(
    () =>
      Object.entries(routes).find(([key]) => {
        parametersNamesRef.current = key
          .matchAll(/\[(?<param>\w+|\w+(-|_)\w+)\]/g)
          .toArray()
          .map((match) => match[1]);

        return formattedPathname.match(getURLMatchRegExp(key));
      }),
    [formattedPathname]
  );

  const parameterValues = matchedRoute
    ? formattedPathname
        .matchAll(getURLMatchRegExp(matchedRoute[0]))
        .toArray()[0]
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
