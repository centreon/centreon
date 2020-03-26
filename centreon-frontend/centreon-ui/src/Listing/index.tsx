import React, { useState, useRef, useEffect, RefObject } from 'react';

import ResizeObserver from 'resize-observer-polyfill';
import isEqual from 'lodash/isEqual';
import clsx from 'clsx';

import { withStyles, makeStyles, Theme } from '@material-ui/core/styles';
import {
  Table,
  TableBody,
  Paper,
  LinearProgress,
  Box,
  TableCell,
  TableRowProps,
  TableRow,
  fade,
  Checkbox,
} from '@material-ui/core';

import IconPowerSettings from '../Icon/IconPowerSettings';
import IconPowerSettingsDisable from '../Icon/IconPowerSettingsDisable';
import IconDelete from '../Icon/IconDelete';
import IconLibraryAdd from '../Icon/IconLibraryAdd';
import ListingHeader from './Header';
import TABLE_COLUMN_TYPES from './ColumnTypes';
import PaginationActions from './PaginationActions';
import StyledPagination from './Pagination';
import Tooltip from '../Tooltip';
import ListingLoadingSkeleton from './Skeleton';

const loadingIndicatorHeight = 3;

const haveSameIds = (a, b): boolean => a.id === b.id;

const BodyTableCell = withStyles({
  root: {
    fontSize: 13,
    lineHeight: 1.4,
    padding: '3px 4px',
  },
})(TableCell);

const useStyles = makeStyles<Theme>((theme) => ({
  paper: {
    width: '100%',
    height: '100%',
    display: 'flex',
    flexDirection: 'column',
    background: 'none',
  },
  actionBar: {
    display: 'flex',
    alignItems: 'center',
  },
  actions: {
    padding: theme.spacing(0.5),
  },
  pagination: {
    marginLeft: 'auto',
    display: 'flex',
    flexDirection: 'row-reverse',
    padding: 0,
  },
  loadingIndicator: {
    width: '100%',
    height: loadingIndicatorHeight,
  },
}));

const cumulativeOffset = (element): number => {
  if (!element || !element.offsetParent) {
    return 0;
  }

  return cumulativeOffset(element.offsetParent) + element.offsetTop;
};

interface Props {
  checkable?: boolean;
  currentPage?;
  columnConfiguration;
  emptyDataMessage?: string;
  rowColorConditions?;
  labelDelete?: string;
  labelDisplayedRows?: (fromToCount) => string;
  labelDuplicate?: string;
  labelEnableDisable?: string;
  labelRowsPerPage?: string;
  limit?: number;
  loading?: boolean;
  loadingDataMessage?: string;
  onDelete?: (rows) => void;
  onDisable?: (rows) => void;
  onDuplicate?: (rows) => void;
  onEnable?: (rows) => void;
  onPaginate?: (event, value) => void;
  onPaginationLimitChanged?: (event) => void;
  onRowClick?: (row) => void;
  onSelectRows?: (rows) => void;
  onSort?: (sortParams) => void;
  paginated?: boolean;
  selectedRows?;
  sorto?: 'asc' | 'desc';
  sortf?: string;
  tableData?;
  totalRows?;
  Actions?: JSX.Element;
  innerScrollDisabled: boolean;
}

const Listing = ({
  limit = 10,
  columnConfiguration,
  tableData = [],
  currentPage = 0,
  totalRows = 0,
  checkable = false,
  emptyDataMessage = 'No results found',
  rowColorConditions = [],
  labelDelete = 'Delete',
  labelDisplayedRows = ({ from, to, count }): string =>
    `${from}-${to} of ${count}`,
  labelDuplicate = 'Duplicate',
  labelEnableDisable = 'Enable / Disable',
  labelRowsPerPage = 'Rows per page',
  loading = false,
  onEnable = (): void => undefined,
  onDelete = (): void => undefined,
  onDisable = (): void => undefined,
  onDuplicate = (): void => undefined,
  onPaginate = (): void => undefined,
  onPaginationLimitChanged = (): void => undefined,
  onRowClick = (): void => undefined,
  onSelectRows = (): void => undefined,
  onSort = (): void => undefined,
  paginated = true,
  selectedRows = [],
  sorto = undefined,
  sortf = undefined,
  Actions,
  innerScrollDisabled = false,
}: Props): JSX.Element => {
  const [tableTopOffset, setTableTopOffset] = useState(0);
  const [hoveredRowId, setHoveredRowId] = useState(null);

  const tableBody = useRef<HTMLTableSectionElement>();

  const classes = useStyles();

  useEffect(() => {
    const ro = new ResizeObserver(() => {
      setTableTopOffset(cumulativeOffset(tableBody.current));
    });

    ro.observe(tableBody.current as Element);
  }, []);

  const selectedRowsInclude = (row): boolean => {
    return !!selectedRows.find((includedRow) => haveSameIds(includedRow, row));
  };

  const handleRequestSort = (_, property): void => {
    const isDesc = sortf === property && sorto === 'desc';

    onSort({
      order: isDesc ? 'asc' : 'desc',
      orderBy: property,
    });
  };

  const selectAllRows = (event): void => {
    if (event.target.checked) {
      onSelectRows(tableData);
      return;
    }

    onSelectRows([]);
  };

  const selectRow = (event, row): void => {
    event.preventDefault();
    event.stopPropagation();

    if (selectedRowsInclude(row)) {
      onSelectRows(selectedRows.filter((entity) => !haveSameIds(entity, row)));
      return;
    }
    onSelectRows([...selectedRows, row]);
  };

  const hoverRow = (id): void => {
    if (hoveredRowId === id) {
      return;
    }
    setHoveredRowId(id);
  };

  const clearHoveredRow = (): void => {
    setHoveredRowId(null);
  };

  const isSelected = (row): boolean => {
    return selectedRowsInclude(row);
  };

  const getColumnCell = ({ row, column }): JSX.Element | null => {
    const cellKey = `${row.id}-${column.id}`;
    const isRowHovered = hoveredRowId === row.id;
    const isRowSelected = isSelected(row);

    const cellByColumnType = {
      [TABLE_COLUMN_TYPES.string]: (): JSX.Element => {
        const { getFormattedString } = column;

        return (
          <BodyTableCell key={cellKey} align="left">
            {getFormattedString(row) || ''}
          </BodyTableCell>
        );
      },
      [TABLE_COLUMN_TYPES.toggler]: (): JSX.Element => (
        <BodyTableCell align="left" key={column.id}>
          {row[column.id] ? (
            <Tooltip
              label={labelEnableDisable}
              onClick={(e): void => {
                e.preventDefault();
                e.stopPropagation();
                onDisable([row]);
              }}
            >
              <IconPowerSettings />
            </Tooltip>
          ) : (
            <Tooltip
              label={labelEnableDisable}
              onClick={(e): void => {
                e.preventDefault();
                e.stopPropagation();
                onEnable([row]);
              }}
            >
              <IconPowerSettingsDisable />
            </Tooltip>
          )}
        </BodyTableCell>
      ),
      [TABLE_COLUMN_TYPES.hoverActions]: (): JSX.Element => (
        <BodyTableCell
          align="right"
          key={column.id}
          style={{
            width: 90,
            position: 'relative',
          }}
        >
          {hoveredRowId === row.id ? (
            <Box
              flexDirection="row"
              display="flex"
              style={{
                marginRight: -4,
                position: 'absolute',
                top: 3,
                right: 0,
              }}
            >
              <Box>
                <Tooltip
                  label={labelDelete}
                  onClick={(e): void => {
                    e.preventDefault();
                    e.stopPropagation();
                    onDelete([row]);
                  }}
                >
                  <IconDelete />
                </Tooltip>
              </Box>
              <Box>
                <Tooltip
                  label={labelDuplicate}
                  onClick={(e): void => {
                    e.preventDefault();
                    e.stopPropagation();
                    onDuplicate([row]);
                  }}
                >
                  <IconLibraryAdd />
                </Tooltip>
              </Box>
            </Box>
          ) : (
            ' '
          )}
        </BodyTableCell>
      ),
      [TABLE_COLUMN_TYPES.component]: (): JSX.Element | null => {
        const { Component, hiddenCondition, width, clickable } = column;

        const isCellHidden = hiddenCondition
          ? hiddenCondition({ row, isRowSelected })
          : false;

        if (isCellHidden) {
          return null;
        }

        return (
          <BodyTableCell
            align="left"
            key={cellKey}
            style={{ width: width || 'auto' }}
            onClick={(e): void => {
              if (!clickable) {
                return;
              }
              e.preventDefault();
              e.stopPropagation();
            }}
          >
            <Component
              row={row}
              isSelected={isRowSelected}
              isHovered={isRowHovered}
            />
          </BodyTableCell>
        );
      },
    };

    return cellByColumnType[column.type]();
  };

  const emptyRows = limit - Math.min(limit, totalRows - currentPage * limit);

  const tableMaxHeight = (): string => {
    return innerScrollDisabled
      ? '100%'
      : `calc(100vh - ${tableTopOffset}px - 25px)`;
  };

  return (
    <>
      {loading && tableData.length > 0 && (
        <LinearProgress className={classes.loadingIndicator} />
      )}
      {(!loading || (loading && tableData.length < 1)) && (
        <div className={classes.loadingIndicator} />
      )}
      <div className={classes.paper}>
        <div className={classes.actionBar}>
          <div className={classes.actions}>{Actions}</div>
          {paginated ? (
            <StyledPagination
              className={classes.pagination}
              rowsPerPageOptions={[10, 20, 30, 40, 50, 60, 70, 80, 90, 100]}
              labelDisplayedRows={labelDisplayedRows}
              labelRowsPerPage={labelRowsPerPage}
              colSpan={3}
              count={totalRows}
              rowsPerPage={limit}
              page={currentPage}
              SelectProps={{
                native: true,
              }}
              onChangePage={onPaginate}
              onChangeRowsPerPage={onPaginationLimitChanged}
              ActionsComponent={PaginationActions}
            />
          ) : null}
        </div>
        <Paper
          style={{
            overflow: 'auto',
            maxHeight: tableMaxHeight(),
          }}
          elevation={1}
        >
          <Table size="small" stickyHeader>
            <ListingHeader
              numSelected={selectedRows.length}
              order={sorto}
              checkable={checkable}
              orderBy={sortf}
              onSelectAllClick={selectAllRows}
              onRequestSort={handleRequestSort}
              rowCount={limit - emptyRows}
              headRows={columnConfiguration}
            />

            <TableBody
              ref={tableBody as RefObject<HTMLTableSectionElement>}
              onMouseLeave={clearHoveredRow}
              style={{
                position: 'relative',
              }}
            >
              {tableData.map((row) => {
                const isRowSelected = isSelected(row);

                return (
                  <MemoizedRow
                    tabIndex={-1}
                    key={row.id}
                    onMouseOver={(): void => hoverRow(row.id)}
                    onFocus={(): void => hoverRow(row.id)}
                    onClick={(): void => {
                      onRowClick(row);
                    }}
                    isHovered={hoveredRowId === row.id}
                    isSelected={isRowSelected}
                    row={row}
                    rowColorConditions={rowColorConditions}
                  >
                    {checkable ? (
                      <BodyTableCell
                        align="left"
                        onClick={(event): void => selectRow(event, row)}
                        padding="checkbox"
                      >
                        <Checkbox
                          checked={isRowSelected}
                          inputProps={{
                            'aria-label': `Select row ${row.id}`,
                          }}
                          color="primary"
                        />
                      </BodyTableCell>
                    ) : null}

                    {columnConfiguration.map((column) =>
                      getColumnCell({
                        column,
                        row,
                      }),
                    )}
                  </MemoizedRow>
                );
              })}
              {tableData.length < 1 && (
                <TableRow tabIndex={-1}>
                  <BodyTableCell
                    colSpan={columnConfiguration.length + 1}
                    align="center"
                  >
                    {loading ? <ListingLoadingSkeleton /> : emptyDataMessage}
                  </BodyTableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </Paper>
      </div>
    </>
  );
};

const useRowStyles = (rowColorConditions): (() => Record<string, string>) =>
  makeStyles<Theme>((theme) => {
    const rowColorClasses = rowColorConditions.reduce(
      (rowColorConditionClasses, { name, color }) => ({
        ...rowColorConditionClasses,
        [name]: {
          backgroundColor: fade(color, 0.2),
        },
      }),
      {},
    );

    return {
      row: {
        cursor: 'pointer',
        '&:hover': {
          backgroundColor: fade(theme.palette.primary.main, 0.08),
        },
      },
      ...rowColorClasses,
    };
  });

interface RowProps {
  children;
  isHovered?: boolean;
  isSelected?: boolean;
  row;
  rowColorConditions;
}

const MemoizedRow = React.memo<RowProps & TableRowProps>(
  ({
    children,
    tabIndex,
    onMouseOver,
    onFocus,
    onClick,
    row,
    rowColorConditions,
  }: RowProps & TableRowProps): JSX.Element => {
    const classes = useRowStyles(rowColorConditions)();

    const specialColor = rowColorConditions.find(({ condition }) =>
      condition(row),
    );

    return (
      <TableRow
        tabIndex={tabIndex}
        onMouseOver={onMouseOver}
        className={clsx([classes.row, classes[specialColor?.name]])}
        onFocus={onFocus}
        onClick={onClick}
      >
        {children}
      </TableRow>
    );
  },
  (prevProps, nextProps) => {
    return (
      isEqual(prevProps.isHovered, nextProps.isHovered) &&
      isEqual(prevProps.isSelected, nextProps.isSelected) &&
      isEqual(prevProps.row, nextProps.row) &&
      isEqual(prevProps.className, nextProps.className)
    );
  },
);

export default Listing;
