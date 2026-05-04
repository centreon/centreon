import { Column, ColumnType } from '@centreon/ui';

import { equals, isNil, T } from 'ramda';
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
      getFormattedString: ({ pollers, name }) => (isNil(pollers) ? '' : name),
      id: 'name',
      label: t(labelName),
      sortable: true,
      type: ColumnType.string
    },
    {
      getFormattedString: ({ type, pollers }) =>
        isNil(pollers)
          ? ''
          : agentTypes.find(({ id }) => equals(id, type)).name,
      id: 'type',
      label: t(labelAgentType),
      type: ColumnType.string
    },
    {
      Component: Poller,
      displaySubItemsCaret: true,
      getRenderComponentOnRowUpdateCondition: T,
      id: 'pollers',
      label: t(labelPoller),
      type: ColumnType.component
    },
    {
      Component: Action,
      clickable: true,
      getRenderComponentOnRowUpdateCondition: T,
      id: 'actions',
      label: t(labelAction),
      type: ColumnType.component,
      width: '80px'
    }
  ];
};
