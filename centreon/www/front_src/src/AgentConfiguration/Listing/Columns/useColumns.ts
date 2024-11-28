import { Column, ColumnType } from '@centreon/ui';
import { T, equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { agentTypes } from '../../Form/useInputs';
import {
  labelAction,
  labelAgentType,
  labelName,
  labelPoller
} from '../../translatedLabels';
import Action from './Action';
import Poller from './Poller';

export const useColumns = (): Array<Column> => {
  const { t } = useTranslation();

  return [
    {
      type: ColumnType.string,
      id: 'name',
      label: t(labelName),
      sortable: true,
      getFormattedString: ({ pollers, name }) => (isNil(pollers) ? '' : name)
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
    },
    {
      type: ColumnType.component,
      id: 'actions',
      label: t(labelAction),
      getRenderComponentOnRowUpdateCondition: T,
      Component: Action,
      width: '80px',
      clickable: true
    }
  ];
};
