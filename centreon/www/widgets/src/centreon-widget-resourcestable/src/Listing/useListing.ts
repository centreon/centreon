import { useEffect } from 'react';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useSnackbar } from '@centreon/ui';

import { Visualization } from './models';
import { labelSelectAtLeastOneColumn } from './translatedLabels';
import {
  defaultSelectedColumnIds,
  defaultSelectedColumnIdsforViewByHost,
  useColumns
} from './Columns';
import {
  limitAtom,
  pageAtom,
  selectedColumnIdsAtom,
  sortOrderAtom,
  sortFieldAtom
} from './atom';
import useLoadResources from './useLoadResources';

export const okStatuses = ['OK', 'UP'];

interface ListingState {
  areColumnsSortable;
  changeLimit;
  changePage;
  changeSort;
  columns;
  data;
  isLoading;
  page;
  resetColumns;
  selectColumns;
  selectedColumnIds;
  sortField;
  sortOrder;
}

interface UseListing {
  displayType;
  refreshCount;
  refreshIntervalToUse;
  resources;
  states;
  statuses;
}

const useListing = ({
  resources,
  states,
  statuses,
  displayType,
  refreshCount,
  refreshIntervalToUse
}: UseListing): ListingState => {
  const { showWarningMessage } = useSnackbar();
  const { t } = useTranslation();

  const [page, setPage] = useAtom(pageAtom);
  const [selectedColumnIds, setSelectedColumnIds] = useAtom(
    selectedColumnIdsAtom
  );

  const [sortField, setSortf] = useAtom(sortFieldAtom);
  const [sortOrder, setSorto] = useAtom(sortOrderAtom);
  // const [visualization, setVisualization] = useAtom(selectedVisualizationAtom);
  const limit = useAtomValue(limitAtom);
  const setLimit = useSetAtom(limitAtom);

  // useEffect(() => {
  //   setVisualization(displayType);
  // }, [displayType]);

  const { data, isLoading } = useLoadResources({
    displayType,
    limit,
    page,
    refreshCount,
    refreshIntervalToUse,
    resources,
    sortField,
    sortOrder,
    states,
    statuses
  });

  const changeSort = ({ sortO, sortF }): void => {
    setSortf(sortF);
    setSorto(sortO);
  };

  const changeLimit = (value): void => {
    setLimit(Number(value));
  };

  const changePage = (updatedPage): void => {
    setPage(updatedPage + 1);
  };

  const resetColumns = (): void => {
    if (equals(displayType, 'host')) {
      setSelectedColumnIds(defaultSelectedColumnIdsforViewByHost);

      return;
    }

    setSelectedColumnIds(defaultSelectedColumnIds);
  };

  useEffect(() => {
    resetColumns();
  }, [displayType]);

  const columns = useColumns({
    visualization: displayType
  });

  const selectColumns = (updatedColumnIds: Array<string>): void => {
    if (updatedColumnIds.length === 0) {
      showWarningMessage(t(labelSelectAtLeastOneColumn));

      return;
    }

    setSelectedColumnIds(updatedColumnIds);
  };

  const areColumnsSortable = equals(displayType, Visualization.All);

  return {
    areColumnsSortable,
    changeLimit,
    changePage,
    changeSort,
    columns,
    data,
    isLoading,
    page,
    resetColumns,
    selectColumns,
    selectedColumnIds,
    sortField,
    sortOrder
  };
};

export default useListing;
