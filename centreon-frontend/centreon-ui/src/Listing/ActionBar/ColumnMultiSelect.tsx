import * as React from 'react';

import { useTranslation } from 'react-i18next';
import { prop } from 'ramda';

import ColumnIcon from '@material-ui/icons/ViewColumn';

import { getVisibleColumns, Props as ListingProps } from '..';
import IconPopoverMultiSelect from '../../InputField/Select/Autocomplete/Multi/IconPopover';
import { labelAddColumns, labelColumns } from '../translatedLabels';
import { SelectEntry } from '../../InputField/Select';
import { Column } from '../models';

type Props = Pick<
  ListingProps<unknown>,
  'columns' | 'columnConfiguration' | 'onSelectColumns' | 'onResetColumns'
>;

const toSelectEntries = (columns: Array<Column>): Array<SelectEntry> => {
  return columns.map(({ id, label }) => ({
    id,
    name: label,
  }));
};

const ColumnMultiSelect = ({
  columns,
  columnConfiguration,
  onSelectColumns,
  onResetColumns,
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const visibleColumns = getVisibleColumns({
    columns,
    columnConfiguration,
  });

  const selectColumnIds = (_, updatedColumns) => {
    onSelectColumns?.(updatedColumns.map(prop('id')));
  };

  return (
    <IconPopoverMultiSelect
      title={t(labelAddColumns)}
      options={toSelectEntries(columns)}
      value={toSelectEntries(visibleColumns)}
      label={t(labelColumns)}
      onChange={selectColumnIds}
      icon={<ColumnIcon />}
      onReset={onResetColumns}
      popperPlacement="bottom-end"
    />
  );
};

export default ColumnMultiSelect;
