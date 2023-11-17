import { MemoizedListing } from '@centreon/ui';

import useListing from './useListing';

const ResourceAccessRulesListing = (): JSX.Element => {
  const {
    changePage,
    changeSort,
    columns,
    data,
    loading,
    page,
    predefinedRowsSelection,
    resetColumns,
    selectedColumnIds,
    selectedRows,
    setLimit,
    setSelectedColumnIds,
    setSelectedRows,
    sortF,
    sortO
  } = useListing();

  return (
    <MemoizedListing
      checkable
      columnConfiguration={{
        selectedColumnIds,
        sortable: true
      }}
      columns={columns}
      currentPage={(page || 1) - 1}
      limit={data?.meta.limit}
      loading={loading}
      memoProps={[
        columns,
        page,
        predefinedRowsSelection,
        sortO,
        sortF,
        selectedRows
      ]}
      predefinedRowsSelection={predefinedRowsSelection}
      rows={data?.result}
      selectedRows={selectedRows}
      sortField={sortF}
      sortOrder={sortO}
      totalRows={data?.meta.total}
      onLimitChange={setLimit}
      onPaginate={changePage}
      onResetColumns={resetColumns}
      onSelectColumns={setSelectedColumnIds}
      onSelectRows={setSelectedRows}
      onSort={changeSort}
    />
  );
};

export default ResourceAccessRulesListing;
