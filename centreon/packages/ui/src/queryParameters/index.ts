import { equals, isEmpty, isNil } from 'ramda';

import type { QueryParameter } from './models';

interface ToRawQueryParametersProps {
  queryParameters: Array<QueryParameter>;
  apiFormat: 'Standard' | 'JSON-LD';
}

const toRawQueryParameter = ({
  name,
  value
}: {
  name: string;
  value: unknown;
}): string => {
  return `${name}=${encodeURIComponent(value as string)}`;
};

const toRawQueryParameters = ({
  queryParameters,
  apiFormat
}: ToRawQueryParametersProps): string =>
  queryParameters
    .filter(({ value }) => !isNil(value) && !isEmpty(value))
    .map(({ name, value }) => ({
      name,
      value: equals(apiFormat, 'JSON-LD') ? value : JSON.stringify(value)
    }))
    .map(toRawQueryParameter)
    .join('&');

export default toRawQueryParameters;
