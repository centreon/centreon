import { useTranslation } from 'react-i18next';

import { Column, ColumnType } from '@centreon/ui';

import { labelHosts, labelServices } from '../translatedLabels';
import { RowProps } from '../models';

import { Name } from './Name';
import Statuses from './Statuses/Statuses';

interface Props {
  groupType: string;
  groupTypeName: string;
}

export const useColumns = ({
  groupTypeName,
  groupType
}: Props): Array<Column> => {
  const { t } = useTranslation();

  return [
    {
      Component: ({ row }: Pick<RowProps, 'row'>) => (
        <Name groupType={groupType} row={row} />
      ),
      clickable: false,
      id: 'name',
      label: t(groupTypeName),
      sortField: 'name',
      sortable: true,
      type: ColumnType.component,
      width: '200px'
    },
    {
      Component: ({ row }: Pick<RowProps, 'row'>) => (
        <Statuses groupType={groupType} resourceType="host" row={row} />
      ),
      clickable: false,
      id: 'host',
      label: t(labelHosts),
      type: ColumnType.component
    },
    {
      Component: ({ row }: Pick<RowProps, 'row'>) => (
        <Statuses groupType={groupType} resourceType="service" row={row} />
      ),
      clickable: false,
      id: 'service',
      label: t(labelServices),
      type: ColumnType.component
    }
  ];
};
