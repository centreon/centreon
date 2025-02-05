import { useTranslation } from 'react-i18next';

import { Column, ColumnType } from '@centreon/ui';
import {
  labelActions,
  labelAlias,
  labelDisabledHosts,
  labelEnableDisable,
  labelEnabledHosts,
  labelName
} from '../../translatedLabels';
import { Actions } from './Actions';
import Hosts from './Hosts/Hosts';
import Status from './Status/Status';

const useColumns = (): {
  columns: Array<Column>;
} => {
  const { t } = useTranslation();

  const columns = [
    {
      disablePadding: false,
      getFormattedString: ({ name }) => name,
      id: 'name',
      label: t(labelName),
      sortField: 'name',
      sortable: true,
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ alias }) => alias,
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
    },
    {
      Component: Actions,
      clickable: true,
      disablePadding: false,
      id: 'actions',
      label: t(labelActions),
      type: ColumnType.component
    },
    {
      Component: Status,
      clickable: true,
      id: 'is_activated',
      sortField: 'is_activated',
      label: t(labelEnableDisable),
      type: ColumnType.component,
      width: 'max-content',
      sortable: true
    }
  ];

  return { columns };
};

export default useColumns;
