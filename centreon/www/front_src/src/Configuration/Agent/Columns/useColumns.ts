import { Column, ColumnType } from '@centreon/ui';
import { T, equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import Name from '../../Common/Columns/Name';
import { agentTypes } from '../utils';
import Poller from './Poller';

import { labelAgentType, labelName, labelPoller } from '../translatedLabels';

export const useColumns = (): Array<Column> => {
  const { t } = useTranslation();

  return [
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
      id: 'type',
      label: t(labelAgentType),
      getFormattedString: ({ type, pollers }) =>
        isNil(pollers) ? '' : agentTypes.find(({ id }) => equals(id, type)).name
    },
    {
      type: ColumnType.component,
      id: 'pollers',
      label: t(labelPoller),
      Component: Poller,
      displaySubItemsCaret: true,
      getRenderComponentOnRowUpdateCondition: T
    }
  ];
};
