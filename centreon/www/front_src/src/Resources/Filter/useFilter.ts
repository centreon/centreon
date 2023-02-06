import { useEffect } from 'react';

import { omit } from 'ramda';
import useDeepCompareEffect from 'use-deep-compare-effect';
import { useAtomValue, useSetAtom } from 'jotai';

import {
  useRequest,
  setUrlQueryParameters,
  getUrlQueryParameters
} from '@centreon/ui';

import { listCustomFilters } from './api';
import { listCustomFiltersDecoder } from './api/decoders';
import { Filter } from './models';
import { build } from './Criterias/searchQueryLanguage';
import {
  applyFilterDerivedAtom,
  currentFilterAtom,
  customFiltersAtom,
  filterWithParsedSearchDerivedAtom,
  getDefaultFilterDerivedAtom,
  searchAtom,
  sendingFilterAtom,
  storedFilterAtom
} from './filterAtoms';
import { CriteriaValue } from './Criterias/models';

export interface FilterState {
  applyCurrentFilter?: () => void;
  currentFilter?: Filter;
  customFilters?: Array<Filter>;
  customFiltersLoading: boolean;
  getCriteriaValue?: (name: string) => CriteriaValue | undefined;
  loadCustomFilters: () => Promise<Array<Filter>>;
  setCriteria?: ({ name, value }: { name: string; value }) => void;
  setCurrentFilter?: (filter: Filter) => void;
  setEditPanelOpen?: (update: boolean) => void;
}

const useFilter = (): void => {
  const { sendRequest: sendListCustomFiltersRequest, sending } = useRequest({
    decoder: listCustomFiltersDecoder,
    request: listCustomFilters
  });

  const currentFilter = useAtomValue(currentFilterAtom);
  const filterWithParsedSearch = useAtomValue(
    filterWithParsedSearchDerivedAtom
  );
  const setCustomFilters = useSetAtom(customFiltersAtom);
  const setSearch = useSetAtom(searchAtom);
  const applyFilter = useSetAtom(applyFilterDerivedAtom);
  const storeFilter = useSetAtom(storedFilterAtom);
  const setSendingFilter = useSetAtom(sendingFilterAtom);

  const defaultFilter = getDefaultFilterDerivedAtom();

  const loadCustomFilters = (): Promise<Array<Filter>> => {
    return sendListCustomFiltersRequest().then(({ result }) => {
      setCustomFilters(result.map(omit(['order'])));

      return result;
    });
  };

  useEffect(() => {
    loadCustomFilters();
  }, []);

  useDeepCompareEffect(() => {
    setSearch(build(currentFilter.criterias));
  }, [currentFilter.criterias]);

  useEffect(() => {
    if (getUrlQueryParameters().fromTopCounter) {
      return;
    }

    storeFilter(filterWithParsedSearch);

    const queryParameters = [
      {
        name: 'filter',
        value: filterWithParsedSearch
      }
    ];

    setUrlQueryParameters(queryParameters);
  }, [filterWithParsedSearch]);

  useEffect(() => {
    if (!getUrlQueryParameters().fromTopCounter) {
      return;
    }

    setUrlQueryParameters([
      {
        name: 'fromTopCounter',
        value: false
      }
    ]);

    applyFilter(defaultFilter);
  }, [getUrlQueryParameters().fromTopCounter]);

  useEffect(() => {
    setSendingFilter(sending);
  }, [sending]);
};

export default useFilter;
