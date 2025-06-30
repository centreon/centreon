import { Column, ColumnType, truncate } from '@centreon/ui';
import { useTranslation } from 'react-i18next';

import Name from '../../Common/Columns/Name';
import Hosts from './Hosts/HostsCount';

import {
  labelAlias,
  labelDisabledHosts,
  labelEnabledHosts,
  labelName
} from '../translatedLabels';

interface Props {
  columns: Array<Column>;
}

const useColumns = (): Props => {
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
      disablePadding: false,
      getFormattedString: ({ alias }) =>
        truncate({ content: alias, maxLength: 50 }),
      id: 'alias',
      label: t(labelAlias),
      sortField: 'alias',
      sortable: true,
      type: ColumnType.string
    },
    {
      Component: Hosts({ enabled: true }),
      id: 'enabled_hosts_count',
      clickable: true,
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
