import { Column, ColumnType } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { AiProviderType } from '../models';
import {
  labelDefaultModel,
  labelName,
  labelType,
  labelUsage
} from '../translatedLabels';
import { aiProviderTypeLabels } from '../utils';
import Usage from './Usage';

const useColumns = (): {
  columns: Array<Column>;
} => {
  const { t } = useTranslation();

  const columns: Array<Column> = [
    {
      disablePadding: false,
      getFormattedString: ({ name }: Record<string, unknown>) => name as string,
      id: 'name',
      label: t(labelName),
      sortable: true,
      sortField: 'name',
      type: ColumnType.string
    },
    {
      getFormattedString: ({ type }: Record<string, unknown>) =>
        t(aiProviderTypeLabels[type as AiProviderType] ?? (type as string)),
      id: 'type',
      label: t(labelType),
      sortable: true,
      sortField: 'type',
      type: ColumnType.string
    },
    {
      getFormattedString: ({ defaultModel }: Record<string, unknown>) =>
        (defaultModel as string) ?? '',
      id: 'default_model',
      label: t(labelDefaultModel),
      type: ColumnType.string
    },
    {
      Component: Usage,
      id: 'usage',
      label: t(labelUsage),
      type: ColumnType.component
    }
    // the enabled/disabled switch column is provided by ConfigurationBase
  ];

  return { columns };
};

export default useColumns;
