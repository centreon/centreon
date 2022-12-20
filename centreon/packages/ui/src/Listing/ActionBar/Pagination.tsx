import { memo } from 'react';

import { equals } from 'ramda';
import { withStyles } from 'tss-react/mui';

import TablePagination from '@mui/material/TablePagination';

const styles = {
  toolbar: {
    height: '32px',
    minHeight: 'auto',
    overflow: 'hidden',
    paddingLeft: 5
  }
};

const Pagination = (props): JSX.Element => (
  <TablePagination component="div" {...props} />
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

export default withStyles(MemoizedPagination, styles);
