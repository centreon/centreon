import { MemoizedListing } from '@centreon/ui';

import useListingColumns from './Columns/useColumns';
import { Actions } from './Actions';
import { useIsViewerUser } from './hooks';
import { PlaylistListingType } from './models';
import useListing from './useListing';

interface ListingProp {
  data?: PlaylistListingType;
  loading: boolean;
}

const Listing = ({ data: listingData, loading }: ListingProp): JSX.Element => {
  const columns = useListingColumns();

  const {
    changePage,
    changeSort,
    page,
    predefinedRowsSelection,
    resetColumns,
    selectedColumnIds,
    selectedRows,
    setLimit,
    setSelectedColumnIds,
    setSelectedRows,
    sortf,
    sorto,
    getRowProperty
  } = useListing({ columns });

  const isViewer = useIsViewerUser();

  return (
    <MemoizedListing
      actions={<Actions />}
      checkable={!isViewer}
      columnConfiguration={{
        selectedColumnIds,
        sortable: true
      }}
      columns={columns}
      currentPage={(page || 1) - 1}
      limit={listingData?.meta.limit}
      loading={loading}
      memoProps={[
        columns,
        page,
        predefinedRowsSelection,
        sorto,
        sortf,
        selectedRows
      ]}
      predefinedRowsSelection={predefinedRowsSelection}
      rows={listingData?.result}
      selectedRows={selectedRows}
      sortField={sortf}
      sortOrder={sorto}
      subItems={{
        canCheckSubItems: false,
        enable: true,
        getRowProperty,
        labelCollapse: 'Collapse',
        labelExpand: 'Expand'
      }}
      totalRows={listingData?.meta.total}
      onLimitChange={setLimit}
      onPaginate={changePage}
      onResetColumns={resetColumns}
      onRowClick={(): void => undefined}
      onSelectColumns={setSelectedColumnIds}
      onSelectRows={setSelectedRows}
      onSort={changeSort}
    />
  );
};

export default Listing;
