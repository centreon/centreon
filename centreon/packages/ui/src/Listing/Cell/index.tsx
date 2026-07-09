import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import {
  alpha,
  TableCell,
  type TableCellBaseProps,
  type TableCellProps,
  type Theme,
  useTheme
} from '@mui/material';

import type { ListingVariant } from '@centreon/ui-context';

import { useAtom } from 'jotai';
import { equals, includes, isNil, reject } from 'ramda';
import type { ElementType } from 'react';
import type { CSSObject } from 'tss-react';

import { IconButton } from '../..';
import { subItemsPivotsAtom } from '../tableAtoms';
import { getTextStyleByViewMode } from '../useStyleTable';
import type { Props as DataCellProps } from './DataCell';

interface GetBackgroundColorProps extends Omit<Props, 'isRowHighlighted'> {
  theme: Theme;
}

interface GetRowHighlightStyleProps {
  isRowHighlighted?: boolean;
  theme: Theme;
  disableRowCondition: (row: Record<string, unknown>) => boolean;
  row?: Record<string, unknown>;
}

const getBackgroundColor = ({
  isRowHovered,
  row,
  rowColorConditions,
  disableRowCondition,
  theme
}: GetBackgroundColorProps): string => {
  if (disableRowCondition(row as Record<string, unknown>)) {
    return alpha(theme.palette.common.black, theme.palette.action.focusOpacity);
  }

  if (isRowHovered) {
    return alpha(theme.palette.primary.main, theme.palette.action.focusOpacity);
  }

  const foundCondition = rowColorConditions?.find(({ condition }) =>
    condition(row as Record<string, unknown>)
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

  if (disableRowCondition(row as Record<string, unknown>)) {
    return { color: alpha(theme.palette.text.secondary, 0.5) };
  }

  return undefined;
};

interface Props
  extends Pick<
      DataCellProps,
      'isRowHovered' | 'rowColorConditions' | 'disableRowCondition'
    >,
    TableCellProps {
  displaySubItemsCaret?: boolean;
  isRowHighlighted?: boolean;
  labelCollapse?: string;
  labelExpand?: string;
  listingVariant?: ListingVariant;
  row?: Record<string, unknown>;
  subItemsRowProperty?: string;
}

const isPivotExistInTheList = (
  id: number | string
): ((list: Array<number | string>) => boolean) => includes(id);

const handleSubItems = ({
  currentSubItemsPivots,
  id
}: {
  currentSubItemsPivots: Array<number | string>;
  id: number | string;
}): Array<number | string> => {
  if (isPivotExistInTheList(id)(currentSubItemsPivots)) {
    return reject(equals(id), currentSubItemsPivots);
  }

  return [...currentSubItemsPivots, id];
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

  const rowId = row?.id as number | string | undefined;

  const click = (e: React.MouseEvent): void => {
    e.preventDefault();
    e.stopPropagation();

    setSubItemsPivots((currentSubItemsPivots) =>
      handleSubItems({
        currentSubItemsPivots,
        id: rowId as number | string
      })
    );
  };

  const isSubItemsExpanded = isPivotExistInTheList(rowId as number | string)(
    subItemsPivots
  );

  const hasSubItems = Boolean(
    subItemsRowProperty && row?.[subItemsRowProperty]
  );

  return (
    <TableCell
      classes={{
        root: 'flex items-center h-full overflow-hidden border-b-1 border-divider px-2 whitespace-nowrap py-0'
      }}
      component={'div' as unknown as ElementType<TableCellBaseProps>}
      style={
        {
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
            disableRowCondition,
            isRowHighlighted,
            row,
            theme
          }),
          ...style
        } as React.CSSProperties
      }
      {...props}
    >
      {displaySubItemsCaret && hasSubItems && (
        <IconButton
          ariaLabel={`${isSubItemsExpanded ? labelCollapse : labelExpand} ${
            row?.id
          }`}
          onClick={click}
          size="small"
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
