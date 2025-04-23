import { equals, isEmpty, not } from 'ramda';

import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import { TableCell, TableCellBaseProps } from '@mui/material';

import PopoverMenu from '../../../PopoverMenu';
import Checkbox from '../../Checkbox';
import { PredefinedRowSelection } from '../../models';
import { labelPredefinedRowsSelectionMenu } from '../../translatedLabels';
import PredefinedSelectionList from '../_internals/PredefinedSelectionList';

import { useStyles } from './SelectActionListingHeaderCell.styles';

export interface SelectActionListingHeaderCellProps {
  onSelectAllClick: (event) => void;
  onSelectRowsWithCondition: (condition) => void;
  predefinedRowsSelection: Array<PredefinedRowSelection>;
  rowCount: number;
  selectedRowCount: number;
}

const SelectActionListingHeaderCell = ({
  rowCount,
  onSelectAllClick,
  selectedRowCount,
  predefinedRowsSelection,
  onSelectRowsWithCondition
}: SelectActionListingHeaderCellProps): JSX.Element => {
  const { classes } = useStyles();

  const hasRows = not(equals(rowCount, 0));

  return (
    <TableCell
      className={classes.checkboxHeaderCell}
      component={'div' as unknown as React.ElementType<TableCellBaseProps>}
    >
      <Checkbox
        checked={hasRows && selectedRowCount === rowCount}
        className={classes.checkbox}
        indeterminate={
          hasRows && selectedRowCount > 0 && selectedRowCount < rowCount
        }
        inputProps={{ 'aria-label': 'Select all' }}
        onChange={onSelectAllClick}
      />
      {not(isEmpty(predefinedRowsSelection)) ? (
        <PopoverMenu
          className={classes.predefinedRowsMenu}
          icon={<ArrowDropDownIcon />}
          title={labelPredefinedRowsSelectionMenu}
        >
          {({ close }): JSX.Element => (
            <PredefinedSelectionList
              close={close}
              predefinedRowsSelection={predefinedRowsSelection}
              onSelectRowsWithCondition={onSelectRowsWithCondition}
            />
          )}
        </PopoverMenu>
      ) : (
        <div className={classes.predefinedRowsMenu} />
      )}
    </TableCell>
  );
};

export { SelectActionListingHeaderCell };
