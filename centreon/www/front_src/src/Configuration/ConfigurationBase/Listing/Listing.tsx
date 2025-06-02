import { Column, MemoizedListing } from '@centreon/ui';

import { useAtom } from 'jotai';
import { JSX } from 'react';
import { Actions } from '../../models';
import ActionsBar from './ActionsBar';
import useColumns from './Columns/useColumns';
import { selectedRowsAtom } from './atoms';
import useListing from './useListing';
interface Props {
  columns: Array<Column>;
  hasWriteAccess: boolean;
  actions?: Actions;
  isLoading: boolean;
  data;
}

const Listing = ({
  columns,
  hasWriteAccess,
  actions,
  isLoading,
  data
}: Props): JSX.Element => {
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
  } = useListing();

  return (
    <MemoizedListing
      checkable={hasWriteAccess && !!actions?.massive}
      actions={
        <ActionsBar
          hasWriteAccess={hasWriteAccess}
          hasMassiveActions={!!actions?.massive}
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
