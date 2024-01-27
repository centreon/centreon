import { useState } from 'react';

import { useTranslation } from 'react-i18next';

import { Column, centreonBaseURL, useSnackbar } from '@centreon/ui';

import { Resource } from '../../../models';
import { getResourcesUrl } from '../../../utils';

import { labelSelectAtLeastThreeColumns } from './translatedLabels';
import { DisplayType, ResourceListing, SortOrder } from './models';
import { defaultSelectedColumnIds, useColumns } from './Columns';
import useLoadResources from './useLoadResources';

interface UseListingState {
  changeLimit: (value) => void;
  changePage: (updatedPage) => void;
  changeSort: ({ sortOrder, sortField }) => void;
  columns: Array<Column>;
  data: ResourceListing | undefined;
  goToResourceStatusPage?: (row) => void;
  isLoading: boolean;
  page: number | undefined;
  resetColumns: () => void;
  selectColumns: (updatedColumnIds: Array<string>) => void;
}

interface UseListingProps {
  changeViewMode?: () => void;
  displayType: DisplayType;
  isFromPreview?: boolean;
  limit?: number;
  refreshCount: number;
  refreshIntervalToUse: number | false;
  resources: Array<Resource>;
  setPanelOptions?: (field, value) => void;
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
  sortOrder,
  changeViewMode,
  isFromPreview
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

  const goToResourceStatusPage = (row): void => {
    if (isFromPreview) {
      return;
    }

    const linkToResourceStatus = getResourcesUrl({
      allResources: resources,
      isForOneResource: true,
      resource: { ...row, parentId: row?.parent?.id },
      states,
      statuses,
      type: displayType
    });

    const mainUrl = window.location.origin + centreonBaseURL;
    const url = mainUrl + linkToResourceStatus;

    changeViewMode?.();
    window.open(url);
  };

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
    goToResourceStatusPage,
    isLoading,
    page,
    resetColumns,
    selectColumns
  };
};

export default useListing;
