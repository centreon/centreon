import { Column, ColumnType } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

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
      getFormattedString: ({ name }): string => name as string,
      id: 'name',
      label: t(labelName),
      sortable: true,
      sortField: 'name',
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ description }): string => description as string,
      id: 'description',
      label: t(labelDescription),
      sortable: true,
      sortField: 'description',
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
