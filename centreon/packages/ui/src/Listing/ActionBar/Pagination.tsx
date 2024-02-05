import { memo } from 'react';

import { equals } from 'ramda';

import TablePagination from '@mui/material/TablePagination';

import { usePaginationStyles } from './ActionBar.styles';

const Pagination = (props): JSX.Element => {
  const { classes } = usePaginationStyles();

  return (
    <TablePagination
      classes={{
        toolbar: classes.toolbar
      }}
      component="div"
      data-testid="Listing Pagination"
      {...props}
    />
  );
};

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
