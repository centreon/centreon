import { PrimitiveAtom } from 'jotai';
import { JsonDecoder } from 'ts.data.json';
import { Column, ListingModel, SearchParameter } from '../../..';
import { ListingSubItems } from '../../Listing/models';

interface CrudPageRootLabels {
  title: string;
  welcome: {
    title: string;
    description?: string;
  };
  actions: {
    create: string;
  };
  listing: {
    search: string;
  };
}

export interface UseListingQueryKeyProps<TFilters> {
  queryKeyName: string;
  filtersAtom: PrimitiveAtom<TFilters>;
}

export interface UseGetItemsProps<TData, TFilters> {
  queryKeyName: string;
  filtersAtom: PrimitiveAtom<TFilters>;
  decoder?: JsonDecoder.Decoder<ListingModel<TData>>;
  baseEndpoint: string;
  getSearchParameters: ({
    search,
    filters
  }: { search: string; filters: TFilters }) => SearchParameter;
}

export interface UseGetItemsState<TData> {
  items: Array<TData>;
  hasItems: boolean;
  isDataEmpty: boolean;
  isLoading: boolean;
  total: number;
}

export interface ListingProps<TData> {
  rows: Array<TData>;
  total: number;
  isLoading: boolean;
  columns: Array<Column>;
  subItems?: ListingSubItems;
  filters: JSX.Element;
}

export interface CrudPageRootProps<TData, TFilters>
  extends UseGetItemsProps<TData, TFilters>,
    ListingProps<TData> {
  labels: CrudPageRootLabels;
}
