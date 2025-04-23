import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { ColumnType } from '../../../';
import { MemoizedListing } from '../../Listing';
import Actions from './Actions/Actions';
import ColumnActions from './Columns/Actions';
import {
  changeSortAtom,
  limitAtom,
  pageAtom,
  sortFieldAtom,
  sortOrderAtom
} from './atoms';
import { ListingProps } from './models';

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
    type: ColumnType.component,
    id: 'actions',
    label: '',
    Component: ColumnActions,
    width: 'min-content',
    clickable: true
  });

  return (
    <MemoizedListing
      actions={<Actions labels={labels} filters={filters} />}
      columns={listingColumns}
      subItems={subItems}
      loading={isLoading}
      rows={rows}
      currentPage={page}
      onPaginate={setPage}
      limit={limit}
      onLimitChange={setLimit}
      totalRows={total}
      sortField={sortField}
      sortOrder={sortOrder}
      onSort={changeSort}
    />
  );
};

export default Listing;
