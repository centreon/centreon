import toRawQueryParameters from '../../queryParameters';
import { QueryParameter } from '../../queryParameters/models';

import getSearchQueryParameterValue from './getSearchQueryParameterValue';
import { Parameters, BuildListingEndpointParameters } from './models';

const getQueryParameters = ({
  sort,
  page,
  limit,
  search,
  customQueryParameters = []
}: Parameters): Array<QueryParameter> => {
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
  customQueryParameters
}: BuildListingEndpointParameters): string => {
  return buildEndpoint({
    baseEndpoint,
    queryParameters: [
      ...getQueryParameters({ ...parameters, customQueryParameters })
    ]
  });
};

export default buildListingEndpoint;
