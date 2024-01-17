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
import { pageAtom } from './atom';

interface UseListingState {
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
}

interface UseListingProps {
  displayType;
  limit;
  refreshCount;
  refreshIntervalToUse;
  resources;
  setPanelOptions;
  sortField;
  sortOrder;
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
  limit,
  sortField,
  sortOrder
}: UseListingProps): UseListingState => {
  const { showWarningMessage } = useSnackbar();
  const { t } = useTranslation();

  const [page, setPage] = useAtom(pageAtom);

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
    setPanelOptions?.('sortField', sortF);
    setPanelOptions?.('sortOrder', sortO);
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
    selectColumns
  };
};

export default useListing;
