import { type Column, ColumnType, truncate } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import type { NamedEntity } from '../models';
import {
  labelAlias,
  labelIpAddress,
  labelMonitoringServer,
  labelName
} from '../translatedLabels';
import Name from './Name';

interface Props {
  columns: Array<Column>;
}

// `getFormattedString` is mandatory: DataCell falls back to an empty string,
// not to `row[id]`.
const useColumns = (): Props => {
  const { t } = useTranslation();

  const columns: Array<Column> = [
    {
      Component: Name,
      disablePadding: false,
      id: 'name',
      label: t(labelName),
      sortable: true,
      sortField: 'name',
      type: ColumnType.component
    },
    {
      disablePadding: false,
      getFormattedString: ({ alias }: Record<string, unknown>) =>
        truncate({ content: (alias as string) ?? '', maxLength: 50 }),
      id: 'alias',
      label: t(labelAlias),
      sortable: true,
      sortField: 'alias',
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ address }: Record<string, unknown>) =>
        address as string,
      id: 'address',
      label: t(labelIpAddress),
      sortable: true,
      sortField: 'address',
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ poller }: Record<string, unknown>) =>
        (poller as NamedEntity)?.name ?? '',
      id: 'poller',
      label: t(labelMonitoringServer),
      type: ColumnType.string
    }
  ];

  return { columns };
};

export default useColumns;
