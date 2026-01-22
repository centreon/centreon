import { Column, ColumnType, truncate } from '@centreon/ui';

import { equals, prop } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  labelCommandLine,
  labelHostUses,
  labelName,
  labelServiceUses,
  labelType
} from '../translatedLabels';
import HostUses from './HostUses';
import ServicetUses from './ServiceUses';

const useColumns = (): {
  columns: Array<Column>;
} => {
  const { t } = useTranslation();

  const columns = [
    {
      disablePadding: false,
      getFormattedString: prop('name'),
      id: 'name',
      label: t(labelName),
      sortable: true,
      sortField: 'name',
      type: ColumnType.string
    },
    {
      getFormattedString: ({ commandLine }) =>
        truncate({ content: commandLine, maxLength: 50 }),
      id: 'command_line',
      label: t(labelCommandLine),
      type: ColumnType.string
    },
    {
      Component: HostUses,
      id: 'host_uses',
      label: t(labelHostUses),
      type: ColumnType.component
    },
    {
      Component: ServicetUses,
      id: 'service_uses',
      label: t(labelServiceUses),
      type: ColumnType.component
    },
    {
      getFormattedString: ({ type }) =>
        t(equals(type, 'Check') ? `${type} ` : type),
      id: 'type',
      label: t(labelType),
      sortable: true,
      type: ColumnType.string
    }
  ];

  return { columns };
};

export default useColumns;
