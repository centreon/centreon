import { useAtom } from 'jotai';
import { append, equals, includes, isNil, reject } from 'ramda';
import { CSSObject } from 'tss-react';

import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import {
  TableCell,
  TableCellBaseProps,
  TableCellProps,
  Theme,
  alpha,
  useTheme
} from '@mui/material';

import { ListingVariant } from '@centreon/ui-context';

import { IconButton } from '../..';
import { subItemsPivotsAtom } from '../tableAtoms';
import { getTextStyleByViewMode } from '../useStyleTable';

import { ElementType } from 'react';
import { Props as DataCellProps } from './DataCell';

interface GetBackgroundColorProps extends Omit<Props, 'isRowHighlighted'> {
  theme: Theme;
}

interface GetRowHighlightStyleProps {
  isRowHighlighted?: boolean;
  theme: Theme;
  disableRowCondition;
  row;
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

const getRowTextColor = ({
  isRowHighlighted,
  theme,
  disableRowCondition,
  row
}: GetRowHighlightStyleProps): CSSObject | undefined => {
  if (isRowHighlighted) {
    return { color: theme.palette.text.primary };
  }

  if (disableRowCondition(row)) {
    return { color: alpha(theme.palette.text.secondary, 0.5) };
  }
};

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
  disableRowCondition,
  isRowHovered,
  isRowHighlighted,
  rowColorConditions,
  listingVariant,
  row,
  style,
  ...props
}: Props): JSX.Element => {
  const theme = useTheme();

  const [subItemsPivots, setSubItemsPivots] = useAtom(subItemsPivotsAtom);

  const { children } = props;

  const rowId = row?.id;

  const click = (e): void => {
    e.preventDefault();
    e.stopPropagation();

    setSubItemsPivots((currentSubItemsPivots) =>
      handleSubItems({ currentSubItemsPivots, id: rowId })
    );
  };

  const isSubItemsExpanded = isPivotExistInTheList(rowId)(subItemsPivots);

  const hasSubItems = subItemsRowProperty && row[subItemsRowProperty];

  return (
    <TableCell
      style={{
        backgroundColor: getBackgroundColor({
          disableRowCondition,
          isRowHovered,
          row,
          rowColorConditions,
          theme
        }),
        ...getTextStyleByViewMode({
          listingVariant,
          theme
        }),
        ...getRowTextColor({
          isRowHighlighted,
          disableRowCondition,
          row,
          theme
        }),
        ...style
      }}
      classes={{
        root: 'flex items-center h-full overflow-hidden border-b-1 border-divider px-2 whitespace-nowrap py-0'
      }}
      component={'div' as unknown as ElementType<TableCellBaseProps>}
      {...props}
    >
      {displaySubItemsCaret && hasSubItems && (
        <IconButton
          ariaLabel={`${isSubItemsExpanded ? labelCollapse : labelExpand} ${
            row.id
          }`}
          size="small"
          onClick={click}
        >
          <ExpandMoreIcon
            className={`transition-transform ${isSubItemsExpanded ? 'rotate-z-180' : 'rotate-z-0'} transform-gpu`}
            fontSize="small"
          />
        </IconButton>
      )}
      {children}
    </TableCell>
  );
};

export default Cell;
