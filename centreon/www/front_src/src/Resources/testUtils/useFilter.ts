import { useEffect } from 'react';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { omit } from 'ramda';
import useDeepCompareEffect from 'use-deep-compare-effect';

import {
  getUrlQueryParameters,
  setUrlQueryParameters,
  useRequest
} from '@centreon/ui';

import { build } from '../Filter/Criterias/searchQueryLanguage';
import { listCustomFilters } from '../Filter/api';
import { listCustomFiltersDecoder } from '../Filter/api/decoders';
import {
  applyCurrentFilterDerivedAtom,
  applyFilterDerivedAtom,
  currentFilterAtom,
  customFiltersAtom,
  editPanelOpenAtom,
  filterWithParsedSearchDerivedAtom,
  getCriteriaValueDerivedAtom,
  searchAtom,
  setCriteriaDerivedAtom,
  storedFilterAtom
} from '../Filter/filterAtoms';
import { Filter } from '../Filter/models';
import { FilterState } from '../Filter/useFilter';

const useFilter = (): FilterState => {
  const {
    sendRequest: sendListCustomFiltersRequest,
    sending: customFiltersLoading
  } = useRequest({
    decoder: listCustomFiltersDecoder,
    request: listCustomFilters
  });

  const [customFilters, setCustomFilters] = useAtom(customFiltersAtom);
  const [currentFilter, setCurrentFilter] = useAtom(currentFilterAtom);
  const filterWithParsedSearch = useAtomValue(
    filterWithParsedSearchDerivedAtom
  );
  const getCriteriaValue = useAtomValue(getCriteriaValueDerivedAtom);
  const defaultFilter = useAtomValue(storedFilterAtom);
  const setSearch = useSetAtom(searchAtom);
  const applyFilter = useSetAtom(applyFilterDerivedAtom);
  const applyCurrentFilter = useSetAtom(applyCurrentFilterDerivedAtom);
  const setCriteria = useSetAtom(setCriteriaDerivedAtom);
  const storeFilter = useSetAtom(storedFilterAtom);
  const setEditPanelOpen = useSetAtom(editPanelOpenAtom);

  const loadCustomFilters = (): Promise<Array<Filter>> => {
    return sendListCustomFiltersRequest().then(({ result }) => {
      setCustomFilters(result.map(omit(['order', 'search_data'])));

      return result;
    });
  };

  useDeepCompareEffect(() => {
    setSearch(build(currentFilter.criterias));
  }, [currentFilter.criterias]);

  useEffect(() => {
    loadCustomFilters();
  }, []);

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

  return {
    applyCurrentFilter,
    currentFilter,
    customFilters,
    customFiltersLoading,
    getCriteriaValue,
    loadCustomFilters,
    setCriteria,
    setCurrentFilter,
    setEditPanelOpen
  };
};

export default useFilter;
