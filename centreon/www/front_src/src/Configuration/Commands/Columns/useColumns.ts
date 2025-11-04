import { prop } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Column, ColumnType } from '@centreon/ui';

import { labelCommandLine, labelName, labelType } from '../translatedLabels';

import Name from './Name';

const useColumns = (): {
  columns: Array<Column>;
} => {
  const { t } = useTranslation();

  const columns = [
    {
      disablePadding: false,
      Component: Name,
      id: 'name',
      label: t(labelName),
      sortField: 'name',
      sortable: true,
      type: ColumnType.component
    },
    {
      type: ColumnType.string,
      id: 'command_line',
      label: t(labelCommandLine),
      getFormattedString: prop('command_line')
    },
    {
      type: ColumnType.string,
      id: 'type',
      label: t(labelType),
      getFormattedString: prop('type')
    }
  ];

  return { columns };
};

export default useColumns;
