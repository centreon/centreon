import { useTranslation } from 'react-i18next';
import { equals, isEmpty, isNil, not, pick } from 'ramda';
import { useAtomValue } from 'jotai';

import ArrowDownwardIcon from '@mui/icons-material/ArrowDownward';
import ArrowUpwardIcon from '@mui/icons-material/ArrowUpward';
import Divider from '@mui/material/Divider';

import { userAtom, ListingVariant } from '@centreon/ui-context';

import { IconButton } from '../..';
import { labelOf, labelRowsPerPage } from '../Listing/translatedLabels';
import { useMemoComponent } from '../../utils';
import { Props as ListingProps } from '../Listing/Listing';

import StyledPagination from './Pagination';
import PaginationActions from './PaginationActions';
import ColumnMultiSelect from './ColumnMultiSelect';
import useStyles from './ActionBar.styles';
import ViewModeSwitch from './ViewModeSwitch';

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
  | 'listingVariant'
  | 'viewerModeConfiguration'
  | 'viewModeConfiguration'
  | 'viewMode'
  | 'visualizationActions'
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
  listingVariant,
  visualizationActions,
  viewModeConfiguration,
  viewMode
}: Props): JSX.Element => {
  console.log('viewerModeConfiguration: ', viewerModeConfiguration);
  const marginWidthTableListing = 30;
  const { classes, cx } = useStyles({
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

  const labelDisplayedRows = ({ from, to, count }): string =>
    `${from}-${to} ${t(labelOf)} ${count}`;

  return useMemoComponent({
    Component: (
      <div className={classes.container}>
        <div className={classes.actions}>
          <div>{actions}</div>
          <div>
            {!isEmpty(viewModeConfiguration) &&
              !isNil(viewModeConfiguration) && (
                <ViewModeSwitch
                  setViewMode={viewModeConfiguration?.onViewModeChange}
                  viewMode={viewMode}
                />
              )}
          </div>
        </div>
        {visualizationActions && <div>{visualizationActions}</div>}
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
      listingVariant,
      themeMode,
      limit,
      pick(
        ['id', 'label', 'disabled', 'width', 'shortLabel', 'sortField'],
        columns
      ),
      columnConfiguration,
      customPaginationClassName,
      viewMode,
      viewModeConfiguration,
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
  listingVariant,
  viewerModeConfiguration,
  visualizationActions,
  viewMode,
  viewModeConfiguration
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
      listingVariant={listingVariant}
      moveTablePagination={moveTablePagination}
      paginated={paginated}
      totalRows={totalRows}
      viewMode={viewMode}
      viewModeConfiguration={viewModeConfiguration}
      viewerModeConfiguration={viewerModeConfiguration}
      visualizationActions={visualizationActions}
      widthToMoveTablePagination={widthToMoveTablePagination}
      onLimitChange={onLimitChange}
      onPaginate={onPaginate}
      onResetColumns={onResetColumns}
      onSelectColumns={onSelectColumns}
    />
  );
};

export default ListingActionBar;
