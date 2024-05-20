import * as React from 'react';

import { append, equals, includes, isNil, omit, reject } from 'ramda';
import { makeStyles } from 'tss-react/mui';
import { CSSObject } from 'tss-react';
import { useAtom } from 'jotai';

import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import {
  alpha,
  TableCell,
  TableCellBaseProps,
  TableCellProps,
  Theme
} from '@mui/material';

import { ListingVariant } from '@centreon/ui-context';

import { IconButton } from '../..';
import { getTextStyleByViewMode } from '../useStyleTable';
import { subItemsPivotsAtom } from '../tableAtoms';

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
  listingVariant?: ListingVariant;
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
      listingVariant
    }
  ) => ({
    caret: {
      transition: theme.transitions.create('transform', {
        duration: theme.transitions.duration.short
      })
    },
    caretLess: {
      transform: 'rotate3d(0,0,1,0deg)'
    },
    caretMore: {
      transform: 'rotate3d(0,0,1,180deg)'
    },
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
      ...getTextStyleByViewMode({ listingVariant, theme }),
      p: getRowHighlightStyle({ isRowHighlighted, theme }),
      padding: theme.spacing(0, 1),
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
  displaySubItemsCaret?: boolean;
  isRowHighlighted?: boolean;
  labelCollapse?: string;
  labelExpand?: string;
  listingVariant?: ListingVariant;
  subItemsRowProperty?: string;
}

const isPivotExistInTheList = (
  id
): ((list: Array<number | string>) => boolean) => includes(id);

const handleSubItems = ({
  currentSubItemsPivots,
  id
}): Array<number | string> => {
  if (isPivotExistInTheList(id)(currentSubItemsPivots)) {
    return reject(equals(id), currentSubItemsPivots);
  }

  return append(id, currentSubItemsPivots);
};

const Cell = ({
  displaySubItemsCaret,
  subItemsRowProperty,
  labelCollapse,
  labelExpand,
  ...props
}: Props): JSX.Element => {
  const { classes, cx } = useStyles(props);

  const [subItemsPivots, setSubItemsPivots] = useAtom(subItemsPivotsAtom);

  const { children } = props;

  const rowId = props.row?.id;

  const click = (e): void => {
    e.preventDefault();
    e.stopPropagation();

    setSubItemsPivots((currentSubItemsPivots) =>
      handleSubItems({ currentSubItemsPivots, id: rowId })
    );
  };

  const isSubItemsExpanded = isPivotExistInTheList(rowId)(subItemsPivots);

  const hasSubItems = subItemsRowProperty && props.row[subItemsRowProperty];

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
          'listingVariant'
        ],
        props
      )}
    >
      {displaySubItemsCaret && hasSubItems && (
        <IconButton
          ariaLabel={`${isSubItemsExpanded ? labelCollapse : labelExpand} ${
            props.row.id
          }`}
          size="small"
          onClick={click}
        >
          <ExpandMoreIcon
            className={cx(
              classes.caret,
              isSubItemsExpanded ? classes.caretMore : classes.caretLess
            )}
            fontSize="small"
          />
        </IconButton>
      )}
      {children}
    </TableCell>
  );
};

export default Cell;
