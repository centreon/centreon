import { MemoizedListing } from '@centreon/ui';

import useListingColumns from './Columns/useColumns';
import { Actions } from './Actions';
import { PlaylistListingType } from './models';
import useListing from './useListing';

interface ListingProp {
  data?: PlaylistListingType;
  loading: boolean;
  openConfig: () => void;
}

const Listing = ({
  data: listingData,
  loading,
  openConfig
}: ListingProp): JSX.Element => {
  const { columns, defaultColumnsIds } = useListingColumns();

  const {
    changePage,
    changeSort,
    page,
    resetColumns,
    selectedColumnIds,
    selectedRows,
    setLimit,
    setSelectedColumnIds,
    setSelectedRows,
    sortf,
    sorto,
    getRowProperty
  } = useListing({ defaultColumnsIds });

  return (
    <MemoizedListing
      actions={<Actions openConfig={openConfig} />}
      columnConfiguration={{
        selectedColumnIds,
        sortable: true
      }}
      columns={columns}
      currentPage={(page || 1) - 1}
      limit={listingData?.meta.limit}
      loading={loading}
      memoProps={[columns, page, sorto, sortf, selectedRows]}
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
      onSelectColumns={setSelectedColumnIds}
      onSelectRows={setSelectedRows}
      onSort={changeSort}
    />
  );
};

export default Listing;
