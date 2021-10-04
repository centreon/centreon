import * as React from 'react';

import { isNil, omit } from 'ramda';

import {
  alpha,
  makeStyles,
  TableCell,
  TableCellBaseProps,
  TableCellProps,
} from '@material-ui/core';

import { Props as DataCellProps } from './DataCell';

const useStyles = makeStyles((theme) => ({
  root: {
    '&:last-child': {
      paddingRight: ({ compact }: Props): number =>
        theme.spacing(compact ? 0 : 2),
    },
    backgroundColor: ({
      isRowHovered,
      row,
      rowColorConditions,
      disableRowCondition,
    }: Props): string => {
      if (disableRowCondition(row)) {
        return alpha(theme.palette.common.black, 0.08);
      }

      if (isRowHovered) {
        return alpha(theme.palette.primary.main, 0.08);
      }

      const foundCondition = rowColorConditions?.find(({ condition }) =>
        condition(row),
      );

      if (!isNil(foundCondition)) {
        return foundCondition.color;
      }

      return 'unset';
    },
    padding: ({ compact }: Props): string =>
      theme.spacing(0, 0, 0, compact ? 0.5 : 1.5),
  },
}));

interface Props
  extends Pick<
      DataCellProps,
      'isRowHovered' | 'row' | 'rowColorConditions' | 'disableRowCondition'
    >,
    TableCellProps {
  compact?: boolean;
}

const Cell = (props: Props): JSX.Element => {
  const classes = useStyles(props);

  const { children } = props;

  return (
    <TableCell
      classes={{ root: classes.root }}
      component={'div' as unknown as React.ElementType<TableCellBaseProps>}
      {...omit(
        [
          'isRowHovered',
          'row',
          'rowColorConditions',
          'compact',
          'disableRowCondition',
        ],
        props,
      )}
    >
      {children}
    </TableCell>
  );
};

export default Cell;
