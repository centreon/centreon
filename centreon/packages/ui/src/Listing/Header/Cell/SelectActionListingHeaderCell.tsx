import { equals, isEmpty, not } from 'ramda';

import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import { TableCell, TableCellBaseProps } from '@mui/material';

import PopoverMenu from '../../../PopoverMenu';
import Checkbox from '../../Checkbox';
import { PredefinedRowSelection } from '../../models';
import { labelPredefinedRowsSelectionMenu } from '../../translatedLabels';
import PredefinedSelectionList from '../_internals/PredefinedSelectionList';

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
  const hasRows = not(equals(rowCount, 0));

  return (
    <TableCell
      className="bg-background-listing-header h-full pt-0 pr-1 pb-0 pl-3 flex flex-row items-center leading=[inherit] justify-start border-b-0"
      component={'div' as unknown as React.ElementType<TableCellBaseProps>}
    >
      <Checkbox
        checked={hasRows && selectedRowCount === rowCount}
        className="text-white"
        indeterminate={
          hasRows && selectedRowCount > 0 && selectedRowCount < rowCount
        }
        slotProps={{ input: { 'aria-label': 'Select all' } }}
        onChange={onSelectAllClick}
      />
      {not(isEmpty(predefinedRowsSelection)) ? (
        <PopoverMenu
          className="text-white"
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
        <div className="text-white" />
      )}
    </TableCell>
  );
};

export { SelectActionListingHeaderCell };
