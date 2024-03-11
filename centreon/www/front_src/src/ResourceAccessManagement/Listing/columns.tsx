import { useTranslation } from 'react-i18next';

import { Column, ColumnType } from '@centreon/ui';

import {
  labelActions,
  labelDescription,
  labelName,
  labelStatus
} from '../translatedLabels';

import { Actions, Activate } from './Actions';

const useListingColumns = (): Array<Column> => {
  const { t } = useTranslation();

  return [
    {
      disablePadding: false,
      getFormattedString: ({ name }): string => name,
      id: 'name',
      label: t(labelName),
      sortField: 'name',
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
      Component: Actions,
      clickable: true,
      disablePadding: true,
      id: 'actions',
      label: t(labelActions),
      type: ColumnType.component
    },
    {
      Component: Activate,
      clickable: true,
      disablePadding: true,
      id: 'isEnabled',
      label: t(labelStatus),
      type: ColumnType.component
    }
  ];
};

export default useListingColumns;
