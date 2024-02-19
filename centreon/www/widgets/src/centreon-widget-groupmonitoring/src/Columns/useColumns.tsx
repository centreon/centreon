import { useTranslation } from 'react-i18next';

import { Column, ColumnType } from '@centreon/ui';

import { Name } from './Name';

interface Props {
  resourceType: string;
  resourceTypeName: string;
}

export const useColumns = ({
  resourceTypeName,
  resourceType
}: Props): Array<Column> => {
  const { t } = useTranslation();

  return [
    {
      Component: ({ row }) => <Name resourceType={resourceType} row={row} />,
      clickable: false,
      id: 'name',
      label: t(resourceTypeName),
      sortField: 'name',
      sortable: true,
      type: ColumnType.component
    }
  ];
};
