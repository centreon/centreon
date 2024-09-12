import { Column, ColumnType } from '@centreon/ui';
import { T, isNil, prop } from 'ramda';
import { useTranslation } from 'react-i18next';
import { labelAgentType, labelName, labelPoller } from '../../translatedLabels';
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
      getFormattedString: prop('type')
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
