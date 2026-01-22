import { equals, keys, values } from 'ramda';

import toRawQueryParameters from '../../queryParameters';
import { QueryParameter } from '../../queryParameters/models';
import { getSearchQueryParameterValue } from './getSearchQueryParameterValue';
import { BuildListingEndpointParameters, Parameters } from './models';

const getQueryParameters = ({
  sort,
  page,
  limit,
  search,
  customQueryParameters = [],
  apiFormat
}: Parameters): Array<QueryParameter> => {
  if (equals(apiFormat, 'JSON-LD')) {
    return [
      { name: 'page', value: page },
      { name: 'itemsPerPage', value: limit },
      { name: `sort[${keys(sort || {})[0]}]`, value: values(sort || {})[0] },
      ...customQueryParameters
    ];
  }

  return [
    { name: 'page', value: page },
    { name: 'limit', value: limit },
    { name: 'sort_by', value: sort },
    {
      name: 'search',
      value: getSearchQueryParameterValue(search)
    },
    ...customQueryParameters
  ];
};

const buildEndpoint = ({
  baseEndpoint,
  queryParameters,
  apiFormat
}): string => {
  return `${baseEndpoint}?${toRawQueryParameters({ apiFormat, queryParameters })}`;
};

const buildListingEndpoint = ({
  baseEndpoint,
  parameters,
  customQueryParameters,
  apiFormat = 'Standard'
}: BuildListingEndpointParameters): string => {
  return buildEndpoint({
    apiFormat,
    baseEndpoint,
    queryParameters: [
      ...getQueryParameters({ ...parameters, apiFormat, customQueryParameters })
    ]
  });
};

export default buildListingEndpoint;
