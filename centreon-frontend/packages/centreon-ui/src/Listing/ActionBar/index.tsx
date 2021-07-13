import * as React from 'react';

import { useTranslation } from 'react-i18next';

import { makeStyles } from '@material-ui/core';

import { ListingProps } from '../..';
import { labelOf, labelRowsPerPage } from '../translatedLabels';

import StyledPagination from './Pagination';
import PaginationActions from './PaginationActions';
import ColumnMultiSelect from './ColumnMultiSelect';

const useStyles = makeStyles((theme) => ({
  actions: {
    padding: theme.spacing(1),
  },
  container: {
    alignItems: 'center',
    display: 'grid',
    gridGap: theme.spacing(1),
    gridTemplateColumns: '1fr auto auto',
    width: '100%',
  },
  pagination: {
    padding: 0,
  },
}));

type Props = Pick<
  ListingProps<unknown>,
  | 'actions'
  | 'onLimitChange'
  | 'onPaginate'
  | 'paginated'
  | 'currentPage'
  | 'limit'
  | 'totalRows'
  | 'columns'
  | 'columnConfiguration'
  | 'onSelectColumns'
  | 'onResetColumns'
>;

const ListingActionBar = ({
  actions,
  onPaginate,
  onLimitChange,
  paginated,
  totalRows,
  currentPage,
  limit,
  columns,
  columnConfiguration,
  onResetColumns,
  onSelectColumns,
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const classes = useStyles();

  const changeRowPerPage = (event): void => {
    onLimitChange?.(event.target.value);
    onPaginate?.(0);
  };

  const changePage = (_, value: number): void => {
    onPaginate?.(value);
  };

  const labelDisplayedRows = ({ from, to, count }): string =>
    `${from}-${to} ${t(labelOf)} ${count}`;

  return (
    <div className={classes.container}>
      <div className={classes.actions}>{actions}</div>
      {columnConfiguration?.selectedColumnIds && (
        <ColumnMultiSelect
          columnConfiguration={columnConfiguration}
          columns={columns}
          onResetColumns={onResetColumns}
          onSelectColumns={onSelectColumns}
        />
      )}
      {paginated && (
        <StyledPagination
          ActionsComponent={PaginationActions}
          SelectProps={{
            native: true,
          }}
          className={classes.pagination}
          colSpan={3}
          count={totalRows}
          labelDisplayedRows={labelDisplayedRows}
          labelRowsPerPage={t(labelRowsPerPage)}
          page={currentPage}
          rowsPerPage={limit}
          rowsPerPageOptions={[10, 20, 30, 40, 50, 60, 70, 80, 90, 100]}
          onPageChange={changePage}
          onRowsPerPageChange={changeRowPerPage}
        />
      )}
    </div>
  );
};

export default ListingActionBar;
