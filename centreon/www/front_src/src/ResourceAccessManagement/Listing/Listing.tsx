import { MemoizedListing } from '@centreon/ui';

import useListing from './useListing';

const ResourceAccessRulesListing = (): JSX.Element => {
  const listing = useListing();

  return (
    <MemoizedListing
      checkable
      columnConfiguration={{
        selectedColumnIds: listing.selectedColumnIds,
        sortable: true
      }}
      columns={listing.columns}
      currentPage={(listing.page || 1) - 1}
      limit={listing.data?.meta.limit}
      loading={listing.loading}
      memoProps={[
        listing.columns,
        listing.page,
        listing.predefinedRowsSelection,
        listing.sortO,
        listing.sortF,
        listing.selectedRows
      ]}
      predefinedRowsSelection={listing.predefinedRowsSelection}
      rows={listing.data?.result}
      selectedRows={listing.selectedRows}
      sortField={listing.sortF}
      sortOrder={listing.sortO}
      totalRows={listing.data?.meta.total}
      onLimitChange={listing.setLimit}
      onPaginate={listing.changePage}
      onResetColumns={listing.resetColumns}
      onSelectRows={listing.setSelectedRows}
      onSort={listing.changeSort}
    />
  );
};

export default ResourceAccessRulesListing;
