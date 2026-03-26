import { useAtom, useAtomValue, useSetAtom } from 'jotai';

import { ColumnType } from '../../../';
import { MemoizedListing } from '../../Listing';
import Actions from './Actions/Actions';
import {
  changeSortAtom,
  limitAtom,
  pageAtom,
  sortFieldAtom,
  sortOrderAtom
} from './atoms';
import ColumnActions from './Columns/Actions';
import type { ListingProps } from './models';

const Listing = <TData extends { id: number; name: string }>({
  rows,
  total,
  isLoading,
  columns,
  subItems,
  labels,
  filters
}: ListingProps<TData> & {
  labels: {
    search: string;
    add: string;
  };
}): JSX.Element => {
  const [page, setPage] = useAtom(pageAtom);
  const [limit, setLimit] = useAtom(limitAtom);
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const changeSort = useSetAtom(changeSortAtom);

  const listingColumns = columns.concat({
    Component: ColumnActions,
    clickable: true,
    id: 'actions',
    label: '',
    type: ColumnType.component,
    width: 'min-content'
  });

  return (
    <MemoizedListing
      actions={<Actions filters={filters} labels={labels} />}
      columns={listingColumns}
      currentPage={page}
      limit={limit}
      loading={isLoading}
      onLimitChange={setLimit}
      onPaginate={setPage}
      onSort={changeSort}
      rows={rows}
      sortField={sortField}
      sortOrder={sortOrder}
      subItems={subItems}
      totalRows={total}
    />
  );
};

export default Listing;
