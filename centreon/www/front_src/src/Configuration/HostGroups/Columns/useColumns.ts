import { Column, ColumnType, truncate } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import {
  labelAlias,
  labelDisabledHosts,
  labelEnabledHosts,
  labelName
} from '../translatedLabels';
import Hosts from './Hosts/HostsCount';
import Name from './Name';

interface Props {
  columns: Array<Column>;
}

const useColumns = (): Props => {
  const { t } = useTranslation();

  const columns = [
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
      getFormattedString: ({ alias }) =>
        truncate({ content: alias, maxLength: 50 }),
      id: 'alias',
      label: t(labelAlias),
      sortable: true,
      sortField: 'alias',
      type: ColumnType.string
    },
    {
      Component: Hosts({ enabled: true }),
      clickable: true,
      id: 'enabled_hosts_count',
      label: t(labelEnabledHosts),
      type: ColumnType.component
    },
    {
      Component: Hosts({ enabled: false }),
      clickable: true,
      id: 'disabled_hosts_count',
      label: t(labelDisabledHosts),
      type: ColumnType.component
    }
  ];

  return { columns };
};

export default useColumns;
