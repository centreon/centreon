import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { MemoizedListing } from '../../Listing';
import {
  changeSortAtom,
  limitAtom,
  pageAtom,
  sortFieldAtom,
  sortOrderAtom
} from './atoms';

const Listing = ({
  rows,
  total,
  isLoading,
  columns,
  subItems
}): JSX.Element => {
  const [page, setPage] = useAtom(pageAtom);
  const [limit, setLimit] = useAtom(limitAtom);
  const sortOrder = useAtomValue(sortOrderAtom);
  const sortField = useAtomValue(sortFieldAtom);
  const changeSort = useSetAtom(changeSortAtom);

  return (
    <MemoizedListing
      columns={columns}
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
