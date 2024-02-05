import { isNil, propEq } from 'ramda';

import { Column, ColumnConfiguration } from './models';

interface Props {
  columnConfiguration?: ColumnConfiguration;
  columns: Array<Column>;
}
export const getVisibleColumns = ({
  columnConfiguration,
  columns
}: Props): Array<Column> => {
  const selectedColumnIds = columnConfiguration?.selectedColumnIds;

  if (isNil(selectedColumnIds)) {
    return columns;
  }

  return selectedColumnIds.map((id) =>
    columns.find(propEq(id, 'id'))
  ) as Array<Column>;
};

export const performanceRowsLimit = 60;
