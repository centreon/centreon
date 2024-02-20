import { MemoizedListing } from '@centreon/ui';

import NoResources from '../../NoResources';

import { WidgetProps } from './models';
import { useGroupMonitoring } from './useGroupMonitoring';
import { useColumns } from './Columns/useColumns';
import { useSetAtom } from 'jotai';
import { statusesAtom } from './atoms';
import { includes, isNotNil } from 'ramda';

const GroupMonitoring = ({
  panelData,
  globalRefreshInterval,
  panelOptions,
  refreshCount,
  isFromPreview,
  setPanelOptions
}: Omit<WidgetProps, 'store'>): JSX.Element => {
  const setStatuses = useSetAtom(statusesAtom);

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
    groupType,
    groupTypeName
  } = useGroupMonitoring({
    globalRefreshInterval,
    isFromPreview,
    panelData,
    panelOptions,
    refreshCount,
    setPanelOptions
  });

  const columns = useColumns({
    groupTypeName,
    groupType
  });

  setStatuses(panelOptions.statuses || [])

  if (!hasResourceTypeDefined) {
    return <NoResources />;
  }

  const columnsToDisplay = ['name', includes('host', panelOptions.resourceTypes) ? 'host' : undefined, includes('service', panelOptions.resourceTypes) ? 'service' : undefined].filter(isNotNil)

  return (
    <MemoizedListing
      columnConfiguration={{
        sortable: false,
        selectedColumnIds: columnsToDisplay
      }}
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
      memoProps={[panelOptions.statuses]}
    />
  );
};

export default GroupMonitoring;
