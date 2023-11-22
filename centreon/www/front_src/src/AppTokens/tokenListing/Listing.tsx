import { MemoizedListing as TokenListing } from '@centreon/ui';

import Actions from './actions';
import Refresh from './actions/refresh';
import { useColumns } from './componentsColumn/useColumns';
import { useTokenListing } from './useTokenListing';
import { useStyles } from './tokenListing.styles';

const Listing = (): JSX.Element | null => {
  const { classes } = useStyles();
  const {
    dataListing,
    changePage,
    changeLimit,
    onSort,
    sortField,
    sortOrder,
    refetch
  } = useTokenListing();

  const { columns, selectedColumnIds, onSelectColumns, onResetColumns } =
    useColumns();

  if (dataListing?.isError) {
    return null;
  }

  return (
    <div className={classes.container}>
      <TokenListing
        innerScrollDisabled
        actions={
          <Actions
            refresh={
              <Refresh isLoading={dataListing?.isLoading} onRefresh={refetch} />
            }
          />
        }
        actionsBarMemoProps={[dataListing?.isLoading]}
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
