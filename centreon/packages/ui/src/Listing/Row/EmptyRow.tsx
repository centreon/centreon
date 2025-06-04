import { ReactNode } from 'react';

import { TableRow } from '@mui/material';

import Cell from '../Cell';

interface EmptyRowProps {
  children: ReactNode;
}

const EmptyRow = ({ children }: EmptyRowProps): JSX.Element => {
  return (
    <TableRow className="contents" component="div" tabIndex={-1}>
      <Cell
        align="center"
        className="col-span-full flex justify-center"
        disableRowCondition={(): boolean => false}
        isRowHovered={false}
      >
        {children}
      </Cell>
    </TableRow>
  );
};

export { EmptyRow };
