import { Column, ColumnType } from '@centreon/ui';
import { prop } from 'ramda';

export const columns: Array<Column> = [
  {
    type: ColumnType.string,
    id: 'name',
    label: 'Name',
    getFormattedString: prop('name'),
    displaySubItemsCaret: true
  },
  {
    type: ColumnType.string,
    id: 'description',
    label: 'Description',
    getFormattedString: prop('description')
  }
];
