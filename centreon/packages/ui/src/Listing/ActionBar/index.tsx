import { useTranslation } from 'react-i18next';
import { isNil, not, pick } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { ListingProps } from '../..';
import { labelOf, labelRowsPerPage } from '../translatedLabels';
import useMemoComponent from '../../utils/useMemoComponent';

import StyledPagination from './Pagination';
import PaginationActions from './PaginationActions';
import ColumnMultiSelect from './ColumnMultiSelect';

const useStyles = makeStyles()((theme) => ({
  actions: {
    padding: theme.spacing(1, 0),
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
  | 'actionsBarMemoProps'
>;

const MemoListingActionBar = ({
  actions,
  paginated,
  totalRows,
  currentPage,
  limit,
  columns,
  columnConfiguration,
  onResetColumns,
  onSelectColumns,
  onPaginate,
  onLimitChange,
  actionsBarMemoProps = [],
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const changeRowPerPage = (event): void => {
    onLimitChange?.(event.target.value);
    onPaginate?.(0);
  };

  const changePage = (_, value: number): void => {
    onPaginate?.(value);
  };

  const labelDisplayedRows = ({ from, to, count }): string =>
    `${from}-${to} ${t(labelOf)} ${count}`;

  return useMemoComponent({
    Component: (
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
              id: labelRowsPerPage,
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
    ),
    memoProps: [
      paginated,
      totalRows,
      currentPage,
      limit,
      pick(
        ['id', 'label', 'disabled', 'width', 'shortLabel', 'sortField'],
        columns,
      ),
      columnConfiguration,
      ...actionsBarMemoProps,
    ],
  });
};

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
  actionsBarMemoProps,
}: Props): JSX.Element | null => {
  if (
    not(paginated) &&
    isNil(actions) &&
    isNil(columnConfiguration?.selectedColumnIds)
  ) {
    return null;
  }

  return (
    <MemoListingActionBar
      actions={actions}
      actionsBarMemoProps={actionsBarMemoProps}
      columnConfiguration={columnConfiguration}
      columns={columns}
      currentPage={currentPage}
      limit={limit}
      paginated={paginated}
      totalRows={totalRows}
      onLimitChange={onLimitChange}
      onPaginate={onPaginate}
      onResetColumns={onResetColumns}
      onSelectColumns={onSelectColumns}
    />
  );
};

export default ListingActionBar;
