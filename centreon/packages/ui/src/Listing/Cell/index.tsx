import * as React from 'react';

import { isNil, omit } from 'ramda';
import { makeStyles } from 'tss-react/mui';
import { CSSObject } from 'tss-react';

import {
  alpha,
  TableCell,
  TableCellBaseProps,
  TableCellProps,
  Theme
} from '@mui/material';

import { ListingVariant } from '@centreon/ui-context';

import { getTextStyleByViewMode } from '../useStyleTable';

import { Props as DataCellProps } from './DataCell';

interface GetBackgroundColorProps extends Omit<Props, 'isRowHighlighted'> {
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

interface StylesProps extends Props {
  isRowHighlighted?: boolean;
  viewMode?: ListingVariant;
}

interface GetRowHighlightStyleProps {
  isRowHighlighted?: boolean;
  theme: Theme;
}

const getRowHighlightStyle = ({
  isRowHighlighted,
  theme
}: GetRowHighlightStyleProps): CSSObject | undefined =>
  isRowHighlighted
    ? {
        color: theme.palette.text.primary
      }
    : undefined;

const useStyles = makeStyles<StylesProps>()(
  (
    theme,
    {
      isRowHovered,
      row,
      rowColorConditions,
      disableRowCondition,
      isRowHighlighted,
      viewMode
    }
  ) => ({
    root: {
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
      'div:nth-of-type(n)': {
        alignItems: 'center',
        display: 'flex'
      },
      height: '100%',
      overflow: 'hidden',
      ...getTextStyleByViewMode({ theme, viewMode }),
      p: getRowHighlightStyle({ isRowHighlighted, theme }),
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
  isRowHighlighted?: boolean;
  viewMode?: ListingVariant;
}

const Cell = (props: Props): JSX.Element => {
  const { classes, cx } = useStyles(props);

  const { children } = props;

  return (
    <TableCell
      classes={{
        root: cx(classes.root)
      }}
      component={'div' as unknown as React.ElementType<TableCellBaseProps>}
      {...omit(
        [
          'isRowHovered',
          'row',
          'rowColorConditions',
          'disableRowCondition',
          'isRowHighlighted',
          'viewMode'
        ],
        props
      )}
    >
      {children}
    </TableCell>
  );
};

export default Cell;
