import { useTranslation } from 'react-i18next';
import { equals, isEmpty, isNil, not, pick } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import ArrowDownwardIcon from '@mui/icons-material/ArrowDownward';
import ArrowUpwardIcon from '@mui/icons-material/ArrowUpward';
import Divider from '@mui/material/Divider';

import { ListingVariant } from '@centreon/ui-context';

import { ListingProps } from '../..';
import { labelOf, labelRowsPerPage } from '../translatedLabels';
import useMemoComponent from '../../utils/useMemoComponent';
import IconButton from '../../Button/Icon/index';

import StyledPagination from './Pagination';
import PaginationActions from './PaginationActions';
import ColumnMultiSelect from './ColumnMultiSelect';

interface StyleProps {
  marginWidthTableListing: number;
  width: number;
}

const useStyles = makeStyles<StyleProps>()(
  (theme, { width, marginWidthTableListing }) => ({
    ModeViewer: {
      paddingLeft: theme.spacing(1)
    },
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
    iconMode: {
      '& .MuiSvgIcon-root': {
        height: theme.spacing(1.5)
      },
      display: 'flex',
      flexDirection: 'column'
    },
    mode: {
      flexDirection: 'column-reverse'
    },
    moving: {
      marginRight: theme.spacing((width - marginWidthTableListing) / 8)
    },
    pagination: {
      '& .MuiToolbar-root': {
        paddingLeft: 0
      },
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
  | 'customPaginationClassName'
  | 'viewMode'
  | 'viewerModeConfiguration'
>;

const MemoListingActionBar = ({
  actions,
  paginated,
  totalRows,
  currentPage,
  limit,
  columns,
  columnConfiguration,
  customPaginationClassName,
  onResetColumns,
  onSelectColumns,
  onPaginate,
  onLimitChange,
  moveTablePagination = false,
  widthToMoveTablePagination = 550,
  actionsBarMemoProps = [],
  viewerModeConfiguration,
  viewMode
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
          {!isEmpty(viewerModeConfiguration) &&
            !isNil(viewerModeConfiguration) && (
              <IconButton
                ariaLabel={
                  t(viewerModeConfiguration?.labelViewerMode ?? '') as string
                }
                className={
                  viewerModeConfiguration?.customStyle
                    ?.customStyleViewerModeContainer
                }
                data-testid={viewerModeConfiguration?.testId}
                size="large"
                title={viewerModeConfiguration?.title}
                onClick={viewerModeConfiguration?.onClick}
              >
                <div
                  className={cx(
                    classes.iconMode,
                    viewerModeConfiguration?.customStyle
                      ?.customStyleViewerModeIcon,
                    {
                      [classes.mode]: equals(viewMode, ListingVariant.extended)
                    }
                  )}
                >
                  <ArrowUpwardIcon fontSize="small" />
                  <Divider />
                  <ArrowDownwardIcon fontSize="small" />
                </div>
              </IconButton>
            )}
          <div className={classes.ModeViewer} />
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
              className={cx(classes.pagination, customPaginationClassName, {
                [classes.moving]: moveTablePagination
              })}
              colSpan={3}
              count={totalRows}
              labelDisplayedRows={labelDisplayedRows}
              labelRowsPerPage={null}
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
      viewMode,
      limit,
      pick(
        ['id', 'label', 'disabled', 'width', 'shortLabel', 'sortField'],
        columns
      ),
      columnConfiguration,
      customPaginationClassName,
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
  widthToMoveTablePagination,
  customPaginationClassName,
  viewMode,
  viewerModeConfiguration
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
      customPaginationClassName={customPaginationClassName}
      limit={limit}
      moveTablePagination={moveTablePagination}
      paginated={paginated}
      totalRows={totalRows}
      viewMode={viewMode}
      viewerModeConfiguration={viewerModeConfiguration}
      widthToMoveTablePagination={widthToMoveTablePagination}
      onLimitChange={onLimitChange}
      onPaginate={onPaginate}
      onResetColumns={onResetColumns}
      onSelectColumns={onSelectColumns}
    />
  );
};

export default ListingActionBar;
