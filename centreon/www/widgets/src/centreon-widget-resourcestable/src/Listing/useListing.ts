import { useAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useSnackbar } from '@centreon/ui';

import { DisplayType } from './models';
import { labelSelectAtLeastThreeColumns } from './translatedLabels';
import {
  defaultSelectedColumnIds,
  defaultSelectedColumnIdsforViewByHost,
  useColumns
} from './Columns';
import useLoadResources from './useLoadResources';
import { pageAtom, sortOrderAtom, sortFieldAtom } from './atom';

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
  sortField;
  sortOrder;
}

interface UseListing {
  displayType;
  limit;
  refreshCount;
  refreshIntervalToUse;
  resources;
  setPanelOptions;
  states;
  statuses;
}

const useListing = ({
  resources,
  states,
  statuses,
  displayType,
  refreshCount,
  refreshIntervalToUse,
  setPanelOptions,
  limit
}: UseListing): ListingState => {
  const { showWarningMessage } = useSnackbar();
  const { t } = useTranslation();

  const [page, setPage] = useAtom(pageAtom);

  const [sortField, setSortf] = useAtom(sortFieldAtom);
  const [sortOrder, setSorto] = useAtom(sortOrderAtom);

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

  const changeSort = ({ sortOrder: sortO, sortField: sortF }): void => {
    setSortf(sortF);
    setSorto(sortO);
  };

  const changeLimit = (value): void => {
    setPanelOptions?.('limit', value);
  };

  const changePage = (updatedPage): void => {
    setPage(updatedPage + 1);
  };

  const resetColumns = (): void => {
    if (equals(displayType, 'host')) {
      setPanelOptions?.(
        'selectedColumnIds',
        defaultSelectedColumnIdsforViewByHost
      );

      return;
    }

    setPanelOptions?.('selectedColumnIds', defaultSelectedColumnIds);
  };

  const columns = useColumns({
    displayType
  });

  const selectColumns = (updatedColumnIds: Array<string>): void => {
    if (updatedColumnIds.length < 3) {
      showWarningMessage(t(labelSelectAtLeastThreeColumns));

      return;
    }

    setPanelOptions?.('selectedColumnIds', updatedColumnIds);
  };

  const areColumnsSortable = equals(displayType, DisplayType.All);

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
    sortField,
    sortOrder
  };
};

export default useListing;
