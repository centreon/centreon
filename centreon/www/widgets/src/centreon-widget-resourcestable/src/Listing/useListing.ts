import { useState } from 'react';

import { useTranslation } from 'react-i18next';

import { Column, useSnackbar } from '@centreon/ui';

import { labelSelectAtLeastThreeColumns } from './translatedLabels';
import { DisplayType, Resource, ResourceListing, SortOrder } from './models';
import { defaultSelectedColumnIds, useColumns } from './Columns';
import useLoadResources from './useLoadResources';

interface UseListingState {
  changeLimit: (value) => void;
  changePage: (updatedPage) => void;
  changeSort: ({ sortOrder, sortField }) => void;
  columns: Array<Column>;
  data: ResourceListing | undefined;
  isLoading: boolean;
  page: number | undefined;
  resetColumns: () => void;
  selectColumns: (updatedColumnIds: Array<string>) => void;
}

interface UseListingProps {
  displayType: DisplayType;
  limit?: number;
  refreshCount: number;
  refreshIntervalToUse: number | false;
  resources: Array<Resource>;
  setPanelOptions: (field, value) => void;
  sortField?: string;
  sortOrder?: SortOrder;
  states: Array<string>;
  statuses: Array<string>;
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

  const [page, setPage] = useState(1);

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

  const resetColumns = (): void => {
    setPanelOptions?.('selectedColumnIds', defaultSelectedColumnIds);
  };

  return {
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
