import { useTranslation } from 'react-i18next';

import { Column, ColumnType, truncate } from '@centreon/ui';

import { equals, prop } from 'ramda';

import {
  labelCommandLine,
  labelHostUses,
  labelName,
  labelServiceUses,
  labelType
} from '../translatedLabels';

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
      sortField: 'name',
      sortable: true,
      type: ColumnType.string
    },
    {
      type: ColumnType.string,
      id: 'command_line',
      label: t(labelCommandLine),
      getFormattedString: ({ commandLine }) =>
        truncate({ content: commandLine, maxLength: 50 })
    },
    {
      type: ColumnType.string,
      id: 'host_uses',
      label: t(labelHostUses),
      getFormattedString: ({ hostsCount, hostTemplatesCount }) =>
        `${hostsCount}(${hostTemplatesCount})`
    },
    {
      type: ColumnType.string,
      id: 'service_uses',
      label: t(labelServiceUses),
      getFormattedString: ({ servicesCount, serviceTemplatesCount }) =>
        `${servicesCount}(${serviceTemplatesCount})`
    },
    {
      type: ColumnType.string,
      id: 'type',
      sortable: true,
      label: t(labelType),
      getFormattedString: ({ type }) =>
        t(equals(type, 'Check') ? `${type} ` : type)
    }
  ];

  return { columns };
};

export default useColumns;
