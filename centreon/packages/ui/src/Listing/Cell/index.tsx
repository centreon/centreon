import * as React from 'react';

import { isNil, omit } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import {
  alpha,
  TableCell,
  TableCellBaseProps,
  TableCellProps,
  Theme
} from '@mui/material';

import { Props as DataCellProps } from './DataCell';

interface GetBackgroundColorProps extends Props {
  theme: Theme;
}

const getBackgroundColor = ({
  isRowHovered,
  row,
  rowColorConditions,
  disableRowCondition,
  theme
}: GetBackgroundColorProps): string => {
  if (disableRowCondition(row)) {
    return alpha(theme.palette.common.black, theme.palette.action.focusOpacity);
  }

  if (isRowHovered) {
    return alpha(theme.palette.primary.main, theme.palette.action.focusOpacity);
  }

  const foundCondition = rowColorConditions?.find(({ condition }) =>
    condition(row)
  );

  if (!isNil(foundCondition)) {
    return foundCondition.color;
  }

  return 'unset';
};

const useStyles = makeStyles<Props>()(
  (
    theme,
    { isRowHovered, row, rowColorConditions, disableRowCondition, compact }
  ) => ({
    root: {
      '&:last-child': {
        // paddingRight: theme.spacing(compact ? 0 : 2)
      },
      alignItems: 'center',
      backgroundColor: getBackgroundColor({
        disableRowCondition,
        isRowHovered,
        row,
        rowColorConditions,
        theme
      }),
      borderBottom: `1px solid ${theme.palette.divider}`,
      display: 'flex',
      height: '100%',
      overflow: 'hidden',
      padding: 0,
      whiteSpace: 'nowrap'
    }
  })
);

interface Props
  extends Pick<
      DataCellProps,
      'isRowHovered' | 'row' | 'rowColorConditions' | 'disableRowCondition'
    >,
    TableCellProps {
  compact?: boolean;
}

const Cell = (props: Props): JSX.Element => {
  const { classes } = useStyles(props);

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
          'disableRowCondition'
        ],
        props
      )}
    >
      {children}
    </TableCell>
  );
};

export default Cell;
