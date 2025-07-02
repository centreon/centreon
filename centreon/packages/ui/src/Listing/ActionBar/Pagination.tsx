import { memo } from 'react';

import { equals } from 'ramda';

import TablePagination from '@mui/material/TablePagination';

const Pagination = (props): JSX.Element => (
  <TablePagination
    classes={{
      toolbar: 'pl-1 overflow-hidden h-8'
    }}
    component="div"
    data-testid="Listing Pagination"
    {...props}
  />
);

const MemoizedPagination = memo(
  Pagination,
  (prevProps, nextProps) =>
    equals(prevProps.rowsPerPage, nextProps.rowsPerPage) &&
    equals(prevProps.page, nextProps.page) &&
    equals(prevProps.count, nextProps.count) &&
    equals(prevProps.labelRowsPerPage, nextProps.labelRowsPerPage) &&
    equals(prevProps.className, nextProps.className)
);

export default MemoizedPagination;
