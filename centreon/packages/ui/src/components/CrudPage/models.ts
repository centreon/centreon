import { PrimitiveAtom } from 'jotai';
import { ReactElement } from 'react';
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

export interface DeleteItem<TData> {
  enabled: boolean;
  deleteEndpoint: (item: TData) => string;
  labels: {
    successMessage:
      | ((item: TData) => string | ReactElement)
      | string
      | ReactElement;
    title: ((item: TData) => string | ReactElement) | string | ReactElement;
    description:
      | ((item: TData) => string | ReactElement)
      | string
      | ReactElement;
    cancel: string;
    confirm: string;
  };
}

export interface GetItem<TItem, TItemForm> {
  baseEndpoint: (id: number) => string;
  decoder?: JsonDecoder.Decoder<TItem>;
  adapter: (item: TItem) => TItemForm;
  itemQueryKey: string;
}

export interface Form<TItem, TItemForm> {
  Form: (props: {
    Buttons: () => JSX.Element;
    initialValues?: TItemForm;
    isLoading?: boolean;
  }) => JSX.Element;
  getItem: GetItem<TItem, TItemForm>;
  labels: {
    add: {
      title: string;
      cancel: string;
      confirm: string;
    };
    update: {
      title: string;
      cancel: string;
      confirm: string;
    };
  };
}

export interface ListingProps<TData> {
  rows: Array<TData>;
  total: number;
  isLoading: boolean;
  columns: Array<Column>;
  subItems?: ListingSubItems;
  filters: JSX.Element;
}

export interface ItemToDelete {
  id: number;
  name: string;
  parent?: { id: number; name: string };
}

export interface CrudPageRootProps<TData, TFilters, TItem, TItemForm>
  extends UseGetItemsProps<TData, TFilters>,
    ListingProps<TData> {
  form: Form<TItem, TItemForm>;
  labels: CrudPageRootLabels;
  deleteItem: DeleteItem<TData>;
}
