import { Column, MemoizedListing } from '@centreon/ui';
import type { PrimitiveAtom } from 'jotai';

import { useAtom } from 'jotai';
import { JSX } from 'react';
import { Actions } from '../../models';
import ActionsBar from './ActionsBar';
import useColumns from './Columns/useColumns';
import { selectedRowsAtom } from './atoms';
import useListing from './useListing';

interface Props<TFilters> {
  columns: Array<Column>;
  hasWriteAccess: boolean;
  actions?: Actions;
  isLoading: boolean;
  filtersAtomKey: string;
  filtersAtom: PrimitiveAtom<TFilters>;
  data;
  selectedColumnIdsAtom: PrimitiveAtom<Array<string>>;
}

const Listing = <TFilters,>({
  columns,
  hasWriteAccess,
  actions,
  isLoading,
  data,
  selectedColumnIdsAtom,
  filtersAtom,
  filtersAtomKey
}: Props<TFilters>): JSX.Element => {
  const [selectedRows, setSelectedRows] = useAtom(selectedRowsAtom);

  const { staticColumns } = useColumns();

  const {
    changePage,
    page,
    changeSort,
    resetColumns,
    setLimit,
    selectColumns,
    sortf,
    sorto,
    selectedColumnIds,
    openEditModal,
    disableRowCondition
  } = useListing({ selectedColumnIdsAtom });

  return (
    <MemoizedListing
      checkable={hasWriteAccess && !!actions?.massive}
      actions={
        <ActionsBar
          hasWriteAccess={hasWriteAccess}
          hasMassiveActions={!!actions?.massive}
          filtersAtom={filtersAtom}
          filtersAtomKey={filtersAtomKey}
        />
      }
      columnConfiguration={{
        selectedColumnIds,
        sortable: true
      }}
      disableRowCondition={disableRowCondition}
      columns={hasWriteAccess ? [...columns, ...staticColumns] : columns}
      currentPage={(page || 1) - 1}
      limit={data?.meta.limit}
      loading={isLoading}
      memoProps={[columns, staticColumns, page, sorto, sortf, selectedRows]}
      rows={data?.result}
      sortField={sortf}
      sortOrder={sorto}
      totalRows={data?.meta.total}
      onLimitChange={setLimit}
      onPaginate={changePage}
      onResetColumns={resetColumns}
      onRowClick={openEditModal}
      onSelectColumns={selectColumns}
      onSort={changeSort}
      selectedRows={selectedRows}
      onSelectRows={setSelectedRows}
    />
  );
};

export default Listing;
