import { useTranslation } from 'react-i18next';
import { isNil, not, pick } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { ListingProps } from '../..';
import { labelOf, labelRowsPerPage } from '../translatedLabels';
import useMemoComponent from '../../utils/useMemoComponent';

import StyledPagination from './Pagination';
import PaginationActions from './PaginationActions';
import ColumnMultiSelect from './ColumnMultiSelect';

interface StyleProps {
  marginWidthTableListing: number;
  width: number;
}

const useStyles = makeStyles<StyleProps>()(
  (theme, { width, marginWidthTableListing }) => ({
    actions: {
      padding: theme.spacing(1, 0)
    },
    container: {
      alignItems: 'center',
      display: 'flex',
      flexWrap: 'wrap',
      justifyContent: 'space-between',
      width: '100%'
    },
    moving: {
      marginRight: theme.spacing((width - marginWidthTableListing) / 8)
    },
    pagination: {
      padding: 0
    },
    selectMenu: {
      '& .MuiMenuItem-root': {
        lineHeight: 1
      }
    },
    subContainer: {
      alignItems: 'center',
      display: 'flex'
    }
  })
);

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
  | 'moveTablePagination'
  | 'widthToMoveTablePagination'
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
  moveTablePagination = false,
  widthToMoveTablePagination = 550,
  actionsBarMemoProps = []
}: Props): JSX.Element => {
  const marginWidthTableListing = 30;
  const { classes, cx } = useStyles({
    marginWidthTableListing,
    width: widthToMoveTablePagination
  });
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
        <div className={classes.actions}>
          <div>{actions}</div>
        </div>
        <div className={classes.subContainer}>
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
                MenuProps: {
                  className: classes.selectMenu
                },
                id: labelRowsPerPage
              }}
              className={cx(classes.pagination, {
                [classes.moving]: moveTablePagination
              })}
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
      </div>
    ),
    memoProps: [
      paginated,
      totalRows,
      currentPage,
      moveTablePagination,
      widthToMoveTablePagination,
      limit,
      pick(
        ['id', 'label', 'disabled', 'width', 'shortLabel', 'sortField'],
        columns
      ),
      columnConfiguration,
      ...actionsBarMemoProps
    ]
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
  moveTablePagination,
  widthToMoveTablePagination
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
      moveTablePagination={moveTablePagination}
      paginated={paginated}
      totalRows={totalRows}
      widthToMoveTablePagination={widthToMoveTablePagination}
      onLimitChange={onLimitChange}
      onPaginate={onPaginate}
      onResetColumns={onResetColumns}
      onSelectColumns={onSelectColumns}
    />
  );
};

export default ListingActionBar;
