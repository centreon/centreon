import ArrowDownwardIcon from '@mui/icons-material/ArrowDownward';
import ArrowUpwardIcon from '@mui/icons-material/ArrowUpward';
import Divider from '@mui/material/Divider';

import { ListingVariant, userAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { equals, isEmpty, isNil, not, pick } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { IconButton, type ListingProps } from '../..';
import { useMemoComponent } from '../../utils';
import { labelOf, labelRowsPerPage } from '../translatedLabels';
import ColumnMultiSelect from './ColumnMultiSelect';
import StyledPagination from './Pagination';
import PaginationActions from './PaginationActions';

interface StyleProps {
  isCursorPaginated: boolean;
  marginWidthTableListing: number;
  width: number;
}

const useStyles = makeStyles<StyleProps>()(
  (theme, { width, marginWidthTableListing, isCursorPaginated }) => ({
    actions: {
      flex: 1,
      padding: theme.spacing(1, 1, 1, 0)
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
    ModeViewer: {
      paddingLeft: theme.spacing(1)
    },
    mode: {
      flexDirection: 'column-reverse'
    },
    moving: {
      marginRight: theme.spacing((width - marginWidthTableListing) / 8)
    },
    pagination: {
      ...(isCursorPaginated && {
        '& .MuiTablePagination-actions': {
          order: 1
        },
        '& .MuiTablePagination-input': {
          order: 2
        }
      }),
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
  | 'isCursorPaginated'
  | 'countConfig'
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
  | 'listingVariant'
  | 'viewerModeConfiguration'
>;

const MemoListingActionBar = ({
  actions,
  paginated,
  isCursorPaginated = false,
  countConfig,
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
  listingVariant
}: Props): JSX.Element => {
  const marginWidthTableListing = 30;
  const { classes, cx } = useStyles({
    isCursorPaginated,
    marginWidthTableListing,
    width: widthToMoveTablePagination
  });
  const { t } = useTranslation();

  const { themeMode } = useAtomValue(userAtom);

  const changeRowPerPage = (event): void => {
    onLimitChange?.(event.target.value);
    onPaginate?.(0);
  };

  const changePage = (_, value: number): void => {
    onPaginate?.(value);
  };

  const labelDisplayedRows = isCursorPaginated
    ? (): string => ''
    : ({ from, to, count }): string => `${from}-${to} ${t(labelOf)} ${count}`;

  const count = countConfig?.count ?? 0;

  const formatNumber = (n: number): string =>
    n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00A0');

  const countLabel = (() => {
    if (!isCursorPaginated || !countConfig) {
      return null;
    }

    if (countConfig.isLoading) {
      return '...';
    }

    return formatNumber(count);
  })();

  const PaginationActionsComponent = useMemo(
    () =>
      function CursorAwarePaginationActions(props) {
        return (
          <PaginationActions {...props} isCursorPaginated={isCursorPaginated} />
        );
      },
    [isCursorPaginated]
  );

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
                disabled={viewerModeConfiguration?.disabled}
                onClick={viewerModeConfiguration?.onClick}
                size="large"
                title={viewerModeConfiguration?.title}
              >
                <div
                  className={cx(
                    classes.iconMode,
                    viewerModeConfiguration?.customStyle
                      ?.customStyleViewerModeIcon,
                    {
                      [classes.mode]: equals(
                        listingVariant,
                        ListingVariant.extended
                      )
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
          {columnConfiguration?.selectedColumnIds &&
            columnConfiguration?.sortable && (
              <ColumnMultiSelect
                columnConfiguration={columnConfiguration}
                columns={columns}
                onResetColumns={onResetColumns}
                onSelectColumns={onSelectColumns}
              />
            )}
          {countLabel !== null && (
            <span className="text-sm px-2 text-[var(--mui-palette-text-secondary)] whitespace-nowrap">
              {!countConfig?.isLoading && (
                <>
                  {formatNumber(
                    Math.min((limit ?? 0) * ((currentPage ?? 0) + 1), count)
                  )}{' '}
                  {t(labelOf)}{' '}
                </>
              )}
              {countLabel}
            </span>
          )}
          {paginated && (
            <StyledPagination
              ActionsComponent={PaginationActionsComponent}
              className={cx(classes.pagination, customPaginationClassName, {
                [classes.moving]: moveTablePagination
              })}
              colSpan={3}
              count={totalRows}
              labelDisplayedRows={labelDisplayedRows}
              labelRowsPerPage={null}
              onPageChange={changePage}
              onRowsPerPageChange={changeRowPerPage}
              page={currentPage}
              rowsPerPage={limit}
              rowsPerPageOptions={[10, 20, 30, 40, 50, 60, 70, 80, 90, 100]}
              SelectProps={{
                id: labelRowsPerPage,
                MenuProps: {
                  className: classes.selectMenu
                }
              }}
            />
          )}
        </div>
      </div>
    ),
    memoProps: [
      paginated,
      isCursorPaginated,
      countConfig?.count,
      countConfig?.isLoading,
      currentPage,
      totalRows,
      moveTablePagination,
      widthToMoveTablePagination,
      listingVariant,
      themeMode,
      limit,
      columns.map(
        pick(['id', 'label', 'disabled', 'width', 'shortLabel', 'sortField'])
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
  isCursorPaginated,
  countConfig,
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
  listingVariant,
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
      countConfig={countConfig}
      currentPage={currentPage}
      customPaginationClassName={customPaginationClassName}
      isCursorPaginated={isCursorPaginated}
      limit={limit}
      listingVariant={listingVariant}
      moveTablePagination={moveTablePagination}
      onLimitChange={onLimitChange}
      onPaginate={onPaginate}
      onResetColumns={onResetColumns}
      onSelectColumns={onSelectColumns}
      paginated={paginated}
      totalRows={totalRows}
      viewerModeConfiguration={viewerModeConfiguration}
      widthToMoveTablePagination={widthToMoveTablePagination}
    />
  );
};

export default ListingActionBar;
