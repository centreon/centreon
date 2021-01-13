import * as React from 'react';

import { equals } from 'ramda';

import TablePagination from '@material-ui/core/TablePagination';
import { withStyles } from '@material-ui/core/styles';

const styles = {
  toolbar: {
    height: '32px',
    minHeight: 'auto',
    paddingLeft: 5,
    overflow: 'hidden',
  },
};

const Pagination = (props): JSX.Element => (
  <TablePagination component="div" {...props} />
);

const MemoizedPagination = React.memo(
  Pagination,
  (prevProps, nextProps) =>
    equals(prevProps.rowsPerPage, nextProps.rowsPerPage) &&
    equals(prevProps.page, nextProps.page) &&
    equals(prevProps.count, nextProps.count) &&
    equals(prevProps.labelRowsPerPage, nextProps.labelRowsPerPage),
);

export default withStyles(styles)(MemoizedPagination);
