import React, { useState, useRef, useEffect } from 'react';

import ResizeObserver from 'resize-observer-polyfill';
import clsx from 'clsx';

import { withStyles, makeStyles, Theme } from '@material-ui/core/styles';
import {
  Table,
  TableBody,
  Paper,
  LinearProgress,
  Box,
  TableCell,
  TableRow,
  fade,
} from '@material-ui/core';

import IconPowerSettings from '../Icon/IconPowerSettings';
import IconPowerSettingsDisable from '../Icon/IconPowerSettingsDisable';
import StyledCheckbox from './Checkbox';
import IconDelete from '../Icon/IconDelete';
import IconLibraryAdd from '../Icon/IconLibraryAdd';
import ListingHeader from './Header';
import TABLE_COLUMN_TYPES from './ColumnTypes';
import PaginationActions from './PaginationActions';
import StyledPagination from './Pagination';
import Tooltip from '../Tooltip';

const loadingIndicatorHeight = 3;

const haveSameIds = (a, b): boolean => a.id === b.id;

const BodyTableCell = withStyles({
  root: {
    fontSize: 13,
    lineHeight: 1.4,
    padding: '3px 4px',
  },
})(TableCell);

const useStyles = (rowColorConditions): (() => Record<string, string>) =>
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
      paper: {
        width: '100%',
        height: '100%',
        display: 'flex',
        flexDirection: 'column',
        background: 'none',
      },
      loadingIndicator: {
        width: '100%',
        height: loadingIndicatorHeight,
      },
      row: {
        cursor: 'pointer',
        '&:hover': {
          backgroundColor: fade(theme.palette.primary.main, 0.08),
        },
      },
      ...rowColorClasses,
    };
  });

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
  onPaginate?: () => void;
  onPaginationLimitChanged?: () => void;
  onRowClick?: (row) => void;
  onSelectRows?: (rows) => void;
  onSort?: (sortParams) => void;
  paginated?: boolean;
  selectedRows?;
  sorto?: 'asc' | 'desc';
  sortf?: string;
  tableData?;
  totalRows?;
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
  loadingDataMessage = 'Loading data',
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
}: Props): JSX.Element => {
  const [tableTopOffset, setTableTopOffset] = useState(0);
  const [hovered, setHovered] = useState(null);

  const tableBody = useRef<Element>();

  const classes = useStyles(rowColorConditions)();

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

  const hoverRow = (id) => (): void => {
    setHovered(id);
  };

  const clearHoveredRow = (): void => {
    if (hovered !== null) {
      setHovered(null);
    }
  };

  const isSelected = (row): boolean => {
    return selectedRowsInclude(row);
  };

  const getColumnCell = ({ row, column }): JSX.Element => {
    const cellByColumnType = {
      [TABLE_COLUMN_TYPES.string]: (): JSX.Element => {
        const { getFormattedString } = column;

        return (
          <BodyTableCell key={column.id} align="left">
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
          {hovered === row.id ? (
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
      [TABLE_COLUMN_TYPES.component]: (): JSX.Element => {
        const { Component, ComponentOnHover, clickable } = column;

        interface CellProps {
          children: React.ReactNode;
          width?: number;
        }

        const Cell = ({ children, width }: CellProps): JSX.Element => (
          <BodyTableCell
            align="left"
            style={{ width }}
            {...(!clickable && {
              onClick: (e): void => {
                e.preventDefault();
                e.stopPropagation();
              },
            })}
          >
            {children}
          </BodyTableCell>
        );

        const displayHoverComponent = hovered === row.id && ComponentOnHover;

        const ComponentToDisplay = displayHoverComponent
          ? ComponentOnHover
          : Component;

        const props = {
          Cell,
          key: column.id,
          row,
          isRowSelected: isSelected(row),
        };

        return <ComponentToDisplay {...props} />;
      },
    };

    return cellByColumnType[column.type]();
  };

  const emptyRows = limit - Math.min(limit, totalRows - currentPage * limit);

  const tableMaxHeight = (): string => {
    return `calc(100vh - ${tableTopOffset}px - 25px)`;
  };

  return (
    <>
      {loading && <LinearProgress className={classes.loadingIndicator} />}
      {!loading && <div className={classes.loadingIndicator} />}
      <div className={classes.paper}>
        {paginated ? (
          <StyledPagination
            rowsPerPageOptions={[10, 20, 30, 40, 50, 60, 70, 80, 90, 100]}
            labelDisplayedRows={labelDisplayedRows}
            labelRowsPerPage={labelRowsPerPage}
            colSpan={3}
            count={totalRows}
            rowsPerPage={limit}
            page={currentPage}
            style={{
              display: 'flex',
              flexDirection: 'row-reverse',
              padding: 0,
            }}
            SelectProps={{
              native: true,
            }}
            onChangePage={onPaginate}
            onChangeRowsPerPage={onPaginationLimitChanged}
            ActionsComponent={PaginationActions}
          />
        ) : null}
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
              ref={tableBody}
              onMouseLeave={clearHoveredRow}
              style={{
                position: 'relative',
              }}
            >
              {tableData.map((row) => {
                const isRowSelected = isSelected(row);

                const specialColor = rowColorConditions.find(({ condition }) =>
                  condition(row),
                );

                return (
                  <TableRow
                    tabIndex={-1}
                    key={row.id}
                    onMouseEnter={hoverRow(row.id)}
                    className={clsx([classes.row, classes[specialColor?.name]])}
                    onClick={(): void => {
                      onRowClick(row);
                    }}
                  >
                    {checkable ? (
                      <BodyTableCell
                        align="left"
                        onClick={(event): void => selectRow(event, row)}
                        padding="checkbox"
                      >
                        <StyledCheckbox
                          checked={isRowSelected}
                          inputProps={{
                            'aria-label': `Select row ${row.id}`,
                          }}
                          color="primary"
                        />
                      </BodyTableCell>
                    ) : null}

                    {columnConfiguration.map((column) =>
                      getColumnCell({ column, row }),
                    )}
                  </TableRow>
                );
              })}
              {tableData.length < 1 && (
                <TableRow tabIndex={-1}>
                  <BodyTableCell
                    colSpan={columnConfiguration.length + 1}
                    align="center"
                  >
                    {loading ? loadingDataMessage : emptyDataMessage}
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

export default Listing;
