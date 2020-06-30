import getSearchParam from './getSearchParam';
import { ListingOptions, Param } from './models';

const buildParam = ({ name, value }): string => {
  return `${name}=${JSON.stringify(value)}`;
};

const buildParams = (params): Array<string> =>
  params
    .filter(({ value }) => value !== undefined && value.length !== 0)
    .map(buildParam)
    .join('&');

const getListingParams = ({
  sort,
  page,
  limit,
  search,
  searchOptions,
}: ListingOptions): Array<Param> => {
  return [
    { name: 'page', value: page },
    { name: 'limit', value: limit },
    { name: 'sort_by', value: sort },
    {
      name: 'search',
      value: getSearchParam({ searchValue: search, searchOptions }),
    },
  ];
};

const buildEndpoint = ({ baseEndpoint, params }): string => {
  return `${baseEndpoint}?${buildParams(params)}`;
};

interface BuildListingParams {
  baseEndpoint?: string;
  searchOptions?: Array<string>;
  params: ListingOptions;
}

const buildListingEndpoint = ({
  baseEndpoint,
  searchOptions,
  params,
}: BuildListingParams): string => {
  return buildEndpoint({
    baseEndpoint,
    params: [...getListingParams({ searchOptions, ...params })],
  });
};

export default buildListingEndpoint;
