import { includes, isNotNil } from 'ramda';

import { MemoizedListing } from '@centreon/ui';

import NoResources from '../../NoResources';

import { FormattedGroup, WidgetProps } from './models';
import { useGroupMonitoring } from './useGroupMonitoring';
import { useColumns } from './Columns/useColumns';

const GroupMonitoring = ({
  panelData,
  globalRefreshInterval,
  panelOptions,
  refreshCount,
  isFromPreview,
  setPanelOptions,
  id,
  dashboardId,
  playlistHash,
  widgetPrefixQuery
}: Omit<WidgetProps, 'store' | 'queryClient'>): JSX.Element => {
  const {
    hasResourceTypeDefined,
    changeLimit,
    changePage,
    changeSort,
    limit,
    page,
    sortField,
    sortOrder,
    listing,
    groupType,
    groupTypeName
  } = useGroupMonitoring({
    dashboardId,
    globalRefreshInterval,
    id,
    isFromPreview,
    panelData,
    panelOptions,
    playlistHash,
    refreshCount,
    setPanelOptions,
    widgetPrefixQuery
  });

  const columns = useColumns({
    groupType,
    groupTypeName,
    isFromPreview
  });

  if (!hasResourceTypeDefined) {
    return <NoResources />;
  }

  const columnsToDisplay = [
    'name',
    includes('host', panelOptions.resourceTypes) ? 'host' : undefined,
    includes('service', panelOptions.resourceTypes) ? 'service' : undefined
  ].filter(isNotNil);

  return (
    <MemoizedListing<FormattedGroup>
      isResponsive
      columnConfiguration={{
        selectedColumnIds: columnsToDisplay,
        sortable: false
      }}
      columns={columns}
      currentPage={page}
      limit={limit}
      rows={listing?.result || []}
      sortField={sortField}
      sortOrder={sortOrder}
      totalRows={listing?.meta.total || 0}
      onLimitChange={changeLimit}
      onPaginate={changePage}
      onSort={changeSort}
    />
  );
};

export default GroupMonitoring;
