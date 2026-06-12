import { Column, ColumnType, truncate } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { AiProviderType } from '../models';
import {
  labelDefault,
  labelDefaultModel,
  labelEndpointUrl,
  labelIsDefault,
  labelName,
  labelType
} from '../translatedLabels';
import { aiProviderTypeLabels } from '../utils';

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
      getFormattedString: ({ endpointUrl }: Record<string, unknown>) =>
        truncate({ content: endpointUrl as string, maxLength: 50 }),
      id: 'endpoint_url',
      label: t(labelEndpointUrl),
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
      getFormattedString: ({ isDefault }: Record<string, unknown>) =>
        isDefault ? t(labelDefault) : '',
      id: 'is_default',
      label: t(labelIsDefault),
      sortable: true,
      sortField: 'is_default',
      type: ColumnType.string
    }
  ];

  return { columns };
};

export default useColumns;
