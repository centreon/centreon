import React, { ReactElement, ReactNode } from 'react';

import { useStyles } from './DataTable.styles';

type DataTableProps = {
  children: ReactNode | Array<ReactNode>;
  isEmpty?: boolean;
  variant?: 'grid';
};

/** *
 * @description DataTable component is used to display a list of items.
 * It supports grids (as cards) and tables (as rows) with filtering, pagination, sorting, and other features
 */
const DataTable = ({
  children,
  variant = 'grid',
  isEmpty = false
}: DataTableProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <div className={classes.dataTableScrollContainer}>
      <div
        className={classes.dataTable}
        data-is-empty={isEmpty}
        data-variant={variant}
      >
        {children}
      </div>
    </div>
  );
};

export { DataTable };
