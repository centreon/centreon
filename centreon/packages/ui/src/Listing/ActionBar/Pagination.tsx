import { memo } from 'react';

import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import TablePagination from '@mui/material/TablePagination';

const useStyles = makeStyles()((theme) => ({
  toolbar: {
    height: theme.spacing(4),
    overflow: 'hidden',
    paddingLeft: 5
  }
}));

const Pagination = (props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <TablePagination
      classes={{
        toolbar: classes.toolbar
      }}
      component="div"
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
