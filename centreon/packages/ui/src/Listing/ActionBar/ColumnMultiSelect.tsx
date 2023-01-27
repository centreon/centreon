import { useTranslation } from 'react-i18next';
import { isNil, prop } from 'ramda';

import ColumnIcon from '@mui/icons-material/ViewColumn';

import { getVisibleColumns, Props as ListingProps } from '..';
import IconPopoverMultiSelect from '../../InputField/Select/IconPopover';
import { labelAddColumns } from '../translatedLabels';
import { SelectEntry } from '../../InputField/Select';
import { Column } from '../models';

type Props = Pick<
  ListingProps<unknown>,
  'columns' | 'columnConfiguration' | 'onSelectColumns' | 'onResetColumns'
>;

const toSelectEntries = (columns: Array<Column>): Array<SelectEntry> => {
  return columns.map(({ id, label, shortLabel }) => ({
    id,
    name: `${label}${!isNil(shortLabel) ? ` (${shortLabel})` : ''}`
  }));
};

const ColumnMultiSelect = ({
  columns,
  columnConfiguration,
  onSelectColumns,
  onResetColumns
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const visibleColumns = getVisibleColumns({
    columnConfiguration,
    columns
  });

  const selectColumnIds = (updatedColumns): void => {
    onSelectColumns?.(updatedColumns.map(prop('id')));
  };

  return (
    <IconPopoverMultiSelect
      icon={<ColumnIcon />}
      options={toSelectEntries(columns)}
      popperPlacement="bottom-end"
      title={t(labelAddColumns)}
      value={toSelectEntries(visibleColumns)}
      onChange={selectColumnIds}
      onReset={onResetColumns}
    />
  );
};

export default ColumnMultiSelect;
