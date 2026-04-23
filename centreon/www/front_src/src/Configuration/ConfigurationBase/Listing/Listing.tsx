import { Column, MemoizedListing } from '@centreon/ui';

import type { PrimitiveAtom } from 'jotai';
import { useAtom } from 'jotai';
import { JSX } from 'react';

import { Actions } from '../../models';
import ActionsBar from './ActionsBar';
import { selectedRowsAtom } from './atoms';
import useColumns from './Columns/useColumns';
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
    limit,
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
      actions={
        <ActionsBar<TFilters>
          filtersAtom={filtersAtom}
          filtersAtomKey={filtersAtomKey}
          hasMassiveActions={!!actions?.massive}
          hasWriteAccess={hasWriteAccess}
        />
      }
      checkable={hasWriteAccess && !!actions?.massive}
      columnConfiguration={{
        selectedColumnIds,
        sortable: true
      }}
      columns={hasWriteAccess ? [...columns, ...staticColumns] : columns}
      currentPage={(page || 1) - 1}
      disableRowCondition={disableRowCondition}
      limit={limit}
      loading={isLoading}
      memoProps={[columns, staticColumns, page, sorto, sortf, selectedRows]}
      onLimitChange={setLimit}
      onPaginate={changePage}
      onResetColumns={resetColumns}
      onRowClick={openEditModal}
      onSelectColumns={selectColumns}
      onSelectRows={setSelectedRows}
      onSort={changeSort}
      rows={data?.result}
      selectedRows={selectedRows}
      sortField={sortf}
      sortOrder={sorto}
      totalRows={data?.meta.total}
    />
  );
};

export default Listing;
