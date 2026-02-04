import { MemoizedListing } from '@centreon/ui';

import { includes, isNotNil } from 'ramda';

import NoResources from '../../NoResources';
import { useColumns } from './Columns/useColumns';
import { FormattedGroup, WidgetProps } from './models';
import { useGroupMonitoring } from './useGroupMonitoring';

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
  widgetPrefixQuery,
  hasDescription
}: WidgetProps): JSX.Element => {
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
    hasDescription,
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
      columnConfiguration={{
        selectedColumnIds: columnsToDisplay,
        sortable: false
      }}
      columns={columns}
      currentPage={page}
      isResponsive
      limit={limit}
      onLimitChange={changeLimit}
      onPaginate={changePage}
      onSort={changeSort}
      rows={listing?.result || []}
      sortField={sortField}
      sortOrder={sortOrder}
      totalRows={listing?.meta.total || 0}
    />
  );
};

export default GroupMonitoring;
