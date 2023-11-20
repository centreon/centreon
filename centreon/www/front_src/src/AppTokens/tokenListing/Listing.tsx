import { MemoizedListing as TokenListing } from '@centreon/ui';

import { useColumns } from './componentsColumn/useColumns';
import { useTokenListing } from './useTokenListing';

const Listing = (): JSX.Element | null => {
  const { dataListing, changePage, changeLimit, onSort, sortField, sortOrder } =
    useTokenListing();

  const { columns, selectedColumnIds, onSelectColumns, onResetColumns } =
    useColumns();

  if (dataListing?.isError) {
    return null;
  }

  return (
    <div style={{ height: 1000, margin: '54px 24px 0px 24px' }}>
      <TokenListing
        columnConfiguration={{ selectedColumnIds, sortable: true }}
        columns={columns}
        currentPage={(dataListing?.page || 1) - 1}
        limit={dataListing?.limit}
        loading={dataListing?.isLoading}
        rows={dataListing?.rows}
        sortField={sortField}
        sortOrder={sortOrder}
        totalRows={dataListing?.total}
        onLimitChange={changeLimit}
        onPaginate={changePage}
        onResetColumns={onResetColumns}
        onSelectColumns={onSelectColumns}
        onSort={onSort}
      />
    </div>
  );
};
export default Listing;
