import { equals } from 'ramda';
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
      { name: 'sort_by', value: sort },
      {
        name: 'search',
        value: getSearchQueryParameterValue(search)
      },
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

const buildEndpoint = ({ baseEndpoint, queryParameters }): string => {
  return `${baseEndpoint}?${toRawQueryParameters(queryParameters)}`;
};

const buildListingEndpoint = ({
  baseEndpoint,
  parameters,
  customQueryParameters,
  apiFormat = 'standard'
}: BuildListingEndpointParameters): string => {
  return buildEndpoint({
    baseEndpoint,
    queryParameters: [
      ...getQueryParameters({ ...parameters, customQueryParameters, apiFormat })
    ]
  });
};

export default buildListingEndpoint;
