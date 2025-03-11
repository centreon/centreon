import { useSetAtom } from 'jotai';

import { MemoizedListing as Listing } from '@centreon/ui';

import Actions from './Actions';
import { useColumns } from './Columns/Columns';
import { selectedRowAtom } from './atoms';
import { useTokenListing } from './useListing';

const TokenListing = (): JSX.Element => {
  const setSelectRow = useSetAtom(selectedRowAtom);

  const {
    dataListing,
    changePage,
    changeLimit,
    onSort,
    sortedField,
    sortOrder
  } = useTokenListing();

  const { columns, selectedColumnIds, onSelectColumns, onResetColumns } =
    useColumns();

  const selectRow = (row): void => {
    setSelectRow(row);
  };

  return (
    <Listing
      innerScrollDisabled
      actions={<Actions />}
      actionsBarMemoProps={[dataListing?.isLoading]}
      columnConfiguration={{ selectedColumnIds, sortable: true }}
      columns={columns}
      currentPage={(dataListing?.page || 1) - 1}
      getId={({ name, user }) => `${name}-${user.id}`}
      limit={dataListing?.limit}
      loading={dataListing?.isLoading}
      memoProps={[]}
      rows={dataListing?.rows}
      sortField={sortedField}
      sortOrder={sortOrder}
      totalRows={dataListing?.total}
      onLimitChange={changeLimit}
      onPaginate={changePage}
      onResetColumns={onResetColumns}
      onRowClick={selectRow}
      onSelectColumns={onSelectColumns}
      onSort={onSort}
    />
  );
};
export default TokenListing;
