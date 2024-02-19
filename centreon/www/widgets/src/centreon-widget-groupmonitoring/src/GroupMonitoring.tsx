import { MemoizedListing } from '@centreon/ui';

import NoResources from '../../NoResources';

import { WidgetProps } from './models';
import { useGroupMonitoring } from './useGroupMonitoring';
import { useColumns } from './Columns/useColumns';

const GroupMonitoring = ({
  panelData,
  globalRefreshInterval,
  panelOptions,
  refreshCount,
  isFromPreview,
  setPanelOptions
}: Omit<WidgetProps, 'store'>): JSX.Element => {
  const {
    hasResourceTypeDefined,
    changeLimit,
    changePage,
    changeSort,
    isLoading,
    limit,
    page,
    sortField,
    sortOrder,
    listing,
    resourceTypeName,
    resourceType
  } = useGroupMonitoring({
    globalRefreshInterval,
    isFromPreview,
    panelData,
    panelOptions,
    refreshCount,
    setPanelOptions
  });

  const columns = useColumns({
    resourceTypeName,
    resourceType
  });

  if (!hasResourceTypeDefined) {
    return <NoResources />;
  }

  return (
    <MemoizedListing
      columns={columns}
      currentPage={page}
      limit={limit}
      loading={isLoading}
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
