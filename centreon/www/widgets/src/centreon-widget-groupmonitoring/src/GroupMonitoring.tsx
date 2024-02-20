import { useSetAtom } from 'jotai';
import { includes, isNotNil } from 'ramda';

import { MemoizedListing } from '@centreon/ui';

import NoResources from '../../NoResources';

import { WidgetProps } from './models';
import { useGroupMonitoring } from './useGroupMonitoring';
import { useColumns } from './Columns/useColumns';
import { statusesAtom } from './atoms';

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
    groupType,
    groupTypeName
  });

  setStatuses(panelOptions.statuses || []);

  if (!hasResourceTypeDefined) {
    return <NoResources />;
  }

  const columnsToDisplay = [
    'name',
    includes('host', panelOptions.resourceTypes) ? 'host' : undefined,
    includes('service', panelOptions.resourceTypes) ? 'service' : undefined
  ].filter(isNotNil);

  return (
    <MemoizedListing
      columnConfiguration={{
        selectedColumnIds: columnsToDisplay,
        sortable: false
      }}
      columns={columns}
      currentPage={page}
      limit={limit}
      loading={isLoading}
      memoProps={[panelOptions.statuses]}
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
