import { useTranslation } from 'react-i18next';

import { Column, ColumnType } from '@centreon/ui';

import {
  labelActions,
  labelDescription,
  labelName,
  labelStatus
} from '../translatedLabels';

// TODO: remove this component once action endpoints are implemented
export const Placeholder = (): JSX.Element => <div />;

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
      // TODO: replace Component once action endpoints are implemented
      Component: Placeholder,
      clickable: true,
      disablePadding: true,
      id: 'actions',
      label: t(labelActions),
      type: ColumnType.component
    },
    {
      // TODO: replace Component once action endpoints are implemented
      Component: Placeholder,
      clickable: true,
      disablePadding: true,
      id: 'isEnabled',
      label: t(labelStatus),
      type: ColumnType.component
    }
  ];
};

export default useListingColumns;
