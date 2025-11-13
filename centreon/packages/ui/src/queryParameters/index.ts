import { equals, isEmpty, isNil } from 'ramda';

import { QueryParameter } from './models';

interface ToRawQueryParametersProps {
  queryParameters: Array<QueryParameter>;
  apiFormat: 'standard' | 'JSON-LD';
}

const toRawQueryParameter = ({ name, value }): string => {
  return `${name}=${encodeURIComponent(value)}`;
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
