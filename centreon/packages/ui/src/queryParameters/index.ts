import { isNil, isEmpty } from 'ramda';

import { QueryParameter } from './models';

const toRawQueryParameter = ({ name, value }): string => {
  return `${name}=${encodeURIComponent(JSON.stringify(value))}`;
};

const toRawQueryParameters = (queryParameters: Array<QueryParameter>): string =>
  queryParameters
    .filter(({ value }) => !isNil(value) && !isEmpty(value))
    .map(toRawQueryParameter)
    .join('&');

export default toRawQueryParameters;
