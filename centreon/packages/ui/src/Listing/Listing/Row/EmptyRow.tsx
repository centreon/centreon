import { ReactNode } from 'react';

import { TableRow } from '@mui/material';

import Cell from '../Cell';

import { useStyles } from './EmptyRow.styles';

interface EmptyRowProps {
  children: ReactNode;
}

const EmptyRow = ({ children }: EmptyRowProps): JSX.Element => {
  const { classes } = useStyles();

  return (
    <TableRow className={classes.emptyDataRow} component="div" tabIndex={-1}>
      <Cell
        align="center"
        className={classes.emptyDataCell}
        disableRowCondition={(): boolean => false}
        isRowHovered={false}
      >
        {children}
      </Cell>
    </TableRow>
  );
};

export { EmptyRow };
