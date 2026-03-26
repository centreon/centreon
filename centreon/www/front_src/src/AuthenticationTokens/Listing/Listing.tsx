import { MemoizedListing } from '@centreon/ui';

import Actions from './Actions';
import { useColumns } from './Columns/Columns';
import useListing from './useListing';

interface Props {
  data?;
  isLoading: boolean;
}

const Listing = ({ data, isLoading }: Props): JSX.Element => {
  const { columns } = useColumns();

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
    disableRowCondition
  } = useListing();

  return (
    <MemoizedListing
      actions={<Actions />}
      columnConfiguration={{
        selectedColumnIds,
        sortable: true
      }}
      columns={columns}
      currentPage={(page || 1) - 1}
      disableRowCondition={disableRowCondition}
      limit={data?.meta.limit}
      loading={isLoading}
      memoProps={[columns, page, sorto, sortf]}
      onLimitChange={setLimit}
      onPaginate={changePage}
      onResetColumns={resetColumns}
      onSelectColumns={selectColumns}
      onSort={changeSort}
      rows={data?.result}
      sortField={sortf}
      sortOrder={sorto}
      totalRows={data?.meta.total}
    />
  );
};

export default Listing;
