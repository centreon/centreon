import { fromPairs } from 'ramda';

import { QueryParameter } from '../models';

const setUrlQueryParameters = (
  queryParameters: Array<QueryParameter>
): void => {
  const urlQueryParameters = new URLSearchParams(window.location.search);

  queryParameters.forEach(({ name, value }) => {
    urlQueryParameters.set(name, JSON.stringify(value));
  });

  window.history.pushState(
    {},
    '',
    `${window.location.pathname}?${urlQueryParameters.toString()}`
  );
};

const getUrlQueryParameters = <
  TQueryParameters extends Record<string, unknown>
>(): TQueryParameters => {
  const urlParams = new URLSearchParams(window.location.search);

  const entries = [...urlParams.entries()].map<[string, string]>(
    ([key, value]) => {
      return [key, JSON.parse(value)];
    }
  );

  return fromPairs(entries) as TQueryParameters;
};

export { setUrlQueryParameters, getUrlQueryParameters };
