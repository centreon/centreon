import { MemoizedListing } from '@centreon/ui';

import { Dashboard } from '../../../api/models';
import { List } from '../../../api/meta.models';

import useColumns from './Columns/useColumns';
import { Actions } from './Actions';
import useListing from './useListing';

interface ListingProp {
  customListingComponent?: JSX.Element;
  data?: List<Dashboard>;
  displayCostumListing?: boolean;
  loading: boolean;
  openConfig: () => void;
}

const Listing = ({
  data: listingData,
  loading,
  openConfig = () => undefined,
  customListingComponent,
  displayCostumListing
}: ListingProp): JSX.Element => {
  const { columns, defaultColumnsIds } = useColumns();

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
      customListingComponent={customListingComponent}
      displayCostumListing={displayCostumListing}
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
