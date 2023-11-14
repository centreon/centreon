import { useAtomValue, useSetAtom } from 'jotai';
import {
  and,
  find,
  isEmpty,
  not,
  omit,
  or,
  pipe,
  propEq,
  symmetricDifference
} from 'ramda';
import { useTranslation } from 'react-i18next';

import { useRequest, useSnackbar } from '@centreon/ui';

import { labelFilterSaved } from '../../../translatedLabels';
import {
  listCustomFilters,
  updateFilter as updateFilterRequest
} from '../../api';
import { listCustomFiltersDecoder } from '../../api/decoders';
import {
  applyFilterDerivedAtom,
  currentFilterAtom,
  customFiltersAtom,
  filtersDerivedAtom
} from '../../filterAtoms';
import { Filter } from '../../models';

interface UseActionFilter {
  canSaveFilter: boolean;
  canSaveFilterAsNew: boolean;
  isNewFilter: boolean;
  loadFiltersAndUpdateCurrent: (data: Filter) => void;
  sendingListCustomFiltersRequest: boolean;
  sendingUpdateFilterRequest: boolean;
  updateFilter: () => void;
}

const useActionFilter = (): UseActionFilter => {
  const { t } = useTranslation();
  const {
    sendRequest: sendUpdateFilterRequest,
    sending: sendingUpdateFilterRequest
  } = useRequest({
    request: updateFilterRequest
  });

  const {
    sendRequest: sendListCustomFiltersRequest,
    sending: sendingListCustomFiltersRequest
  } = useRequest({
    decoder: listCustomFiltersDecoder,
    request: listCustomFilters
  });

  const currentFilter = useAtomValue(currentFilterAtom);
  const filters = useAtomValue(filtersDerivedAtom);
  const applyFilter = useSetAtom(applyFilterDerivedAtom);
  const setCustomFilters = useSetAtom(customFiltersAtom);

  const { showSuccessMessage } = useSnackbar();

  const isFilterDirty = (): boolean => {
    const areValuesEqual = pipe(symmetricDifference, isEmpty) as (
      a,
      b
    ) => boolean;
    const retrievedFilter = find(propEq(currentFilter.id, 'id'), filters);

    const criteriasCurrentFilter = currentFilter.criterias?.map((element) => ({
      ...element
    }));
    const criteriasFilters = (retrievedFilter?.criterias || [])?.map(
      (element) => element
    );

    return !areValuesEqual(criteriasCurrentFilter, criteriasFilters);
  };

  const isNewFilter = currentFilter.id === '';
  const canSaveFilter = and(isFilterDirty(), not(isNewFilter));
  const canSaveFilterAsNew = or(isFilterDirty(), isNewFilter);

  const loadCustomFilters = (): Promise<Array<Filter>> => {
    return sendListCustomFiltersRequest().then(({ result }) => {
      setCustomFilters(result.map(omit(['order'])));

      return result;
    });
  };

  const loadFiltersAndUpdateCurrent = (newFilter: Filter): void => {
    loadCustomFilters?.().then(() => {
      applyFilter(newFilter);
    });
  };

  const updateFilter = (): void => {
    sendUpdateFilterRequest({
      filter: omit(['id'], currentFilter),
      id: currentFilter.id
    }).then((savedFilter) => {
      showSuccessMessage(t(labelFilterSaved));
      loadFiltersAndUpdateCurrent(omit(['order'], savedFilter));
    });
  };

  return {
    canSaveFilter,
    canSaveFilterAsNew,
    isNewFilter,
    loadFiltersAndUpdateCurrent,
    sendingListCustomFiltersRequest,
    sendingUpdateFilterRequest,
    updateFilter
  };
};

export default useActionFilter;
