import { useEffect, useMemo, useState } from 'react';

import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { Column, useSnackbar } from '@centreon/ui';

import { CommonWidgetProps, Resource, SortOrder } from '../../../models';
import { getResourcesUrl, goToUrl } from '../../../utils';
import { PanelOptions } from '../models';

import { labelSelectAtLeastThreeColumns } from './translatedLabels';
import { DisplayType, ResourceListing } from './models';
import { defaultSelectedColumnIds, useColumns } from './Columns';
import useLoadResources from './useLoadResources';

interface UseListingState {
  changeLimit: (value) => void;
  changePage: (updatedPage) => void;
  changeSort: ({ sortOrder, sortField }) => void;
  columns: Array<Column>;
  data: ResourceListing | undefined;
  goToResourceStatusPage?: (row) => void;
  hasMetaService: boolean;
  isLoading: boolean;
  page: number | undefined;
  resetColumns: () => void;
  selectColumns: (updatedColumnIds: Array<string>) => void;
}

interface UseListingProps
  extends Pick<
    CommonWidgetProps<PanelOptions>,
    'dashboardId' | 'id' | 'playlistHash' | 'widgetPrefixQuery'
  > {
  changeViewMode?: (displayType) => void;
  displayType: DisplayType;
  isFromPreview?: boolean;
  limit?: number;
  refreshCount: number;
  refreshIntervalToUse: number | false;
  resources: Array<Resource>;
  setPanelOptions?: (partialOptions: object) => void;
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
  isFromPreview,
  id,
  dashboardId,
  playlistHash,
  widgetPrefixQuery
}: UseListingProps): UseListingState => {
  const { showWarningMessage } = useSnackbar();
  const { t } = useTranslation();

  const [page, setPage] = useState(1);

  const { data, isLoading } = useLoadResources({
    dashboardId,
    displayType,
    id,
    limit,
    page,
    playlistHash,
    refreshCount,
    refreshIntervalToUse,
    resources,
    sortField,
    sortOrder,
    states,
    statuses,
    widgetPrefixQuery
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

    changeViewMode?.(displayType);
    goToUrl(linkToResourceStatus)();
  };

  const hasMetaService = useMemo(
    () =>
      resources.some(({ resourceType }) =>
        equals(resourceType, 'meta-service')
      ),
    [resources]
  );

  const changeSort = (sortParameters): void => {
    setPanelOptions?.(sortParameters);
  };

  const changeLimit = (value): void => {
    setPanelOptions?.({ limit: value });
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

    setPanelOptions?.({ selectedColumnIds: updatedColumnIds });
  };

  const resetColumns = (): void => {
    setPanelOptions?.({ selectedColumnIds: defaultSelectedColumnIds });
  };

  useEffect(() => {
    if (!hasMetaService) {
      return;
    }

    setPanelOptions?.({ displayType: DisplayType.All });
  }, [hasMetaService]);

  return {
    changeLimit,
    changePage,
    changeSort,
    columns,
    data,
    goToResourceStatusPage,
    hasMetaService,
    isLoading,
    page,
    resetColumns,
    selectColumns
  };
};

export default useListing;
