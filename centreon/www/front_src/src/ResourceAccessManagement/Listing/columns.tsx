import { useTranslation } from 'react-i18next';

import { Column, ColumnType } from '@centreon/ui';

import {
  labelActions,
  labelDescription,
  labelRules,
  labelStatus
} from '../translatedLabels';

export const useListingColumns = (): Array<Column> => {
  const { t } = useTranslation();

  return [
    {
      disablePadding: false,
      getFormattedString: ({ name }): string => name,
      id: 'rule',
      label: t(labelRules),
      sortField: 'rule',
      sortable: true,
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ description }): string => description,
      id: 'description',
      label: t(labelDescription),
      sortField: 'description',
      sortable: true,
      type: ColumnType.string
    },
    {
      // Component:
      clickable: true,
      disablePadding: true,
      id: 'actions',
      label: t(labelActions),
      type: ColumnType.component
    },
    {
      // Component:
      clickable: true,
      disablePadding: true,
      id: 'isEnabled',
      label: t(labelStatus),
      // sorting ?
      type: ColumnType.component
    }
  ];
};
