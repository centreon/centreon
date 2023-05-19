import React, { ReactNode } from 'react';

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
const DataTable: React.FC<DataTableProps> = ({
  children,
  variant = 'grid',
  isEmpty = false
}): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div
      className={classes.dataTable}
      data-is-empty={isEmpty}
      data-variant={variant}
    >
      {children}
    </div>
  );
};

export { DataTable };
