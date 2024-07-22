import { useTranslation } from 'react-i18next';

import { MemoizedListing } from '@centreon/ui';

import { getColumns } from './Columns';
import ActionsBar from './ActionsBar/ActionsBar';
import useListing from './useListing';
import DeleteConnectorDialog from './Columns/Actions/DeleteDialog';
import DuplicateConnectorDialog from './Columns/Actions/DuplicateDialog';

const Listing = (): JSX.Element => {
  const { t } = useTranslation();
  const { columns } = getColumns({ t });

  const {
    changePage,
    changeSort,
    page,
    resetColumns,
    setLimit,
    selectColumns,
    sortf,
    sorto,
    isLoading,
    data,
    selectedColumnIds
  } = useListing();

  return (
    <>
      <MemoizedListing
        actions={<ActionsBar />}
        columnConfiguration={{
          selectedColumnIds,
          sortable: true
        }}
        columns={columns}
        currentPage={(page || 1) - 1}
        limit={data?.meta.limit}
        loading={isLoading}
        memoProps={[columns, page, sorto, sortf]}
        rows={data?.result}
        sortField={sortf}
        sortOrder={sorto}
        totalRows={data?.meta.total}
        onLimitChange={setLimit}
        onPaginate={changePage}
        onResetColumns={resetColumns}
        onSelectColumns={selectColumns}
        onSort={changeSort}
      />
      <DeleteConnectorDialog />
      <DuplicateConnectorDialog />
    </>
  );
};

export default Listing;
