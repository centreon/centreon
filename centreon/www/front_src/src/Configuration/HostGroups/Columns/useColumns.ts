import { useTranslation } from 'react-i18next';

import { Column, ColumnType } from '@centreon/ui';
import {
  labelAlias,
  labelDisabledHosts,
  labelEnabledHosts,
  labelName
} from '../translatedLabels';
import { truncateString } from '../utils';
import Hosts from './Hosts/HostsCount';
import Name from './Name/Name';

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
      getFormattedString: ({ alias }) => truncateString(alias),
      id: 'alias',
      label: t(labelAlias),
      sortField: 'alias',
      sortable: true,
      type: ColumnType.string
    },
    {
      Component: Hosts({ enabled: true }),
      id: 'enabled_hosts_count',
      label: t(labelEnabledHosts),
      type: ColumnType.component
    },
    {
      Component: Hosts({ enabled: false }),
      id: 'disabled_hosts_count',
      label: t(labelDisabledHosts),
      type: ColumnType.component
    }
  ];

  return { columns };
};

export default useColumns;
