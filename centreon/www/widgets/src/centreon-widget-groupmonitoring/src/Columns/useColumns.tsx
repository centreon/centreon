import { useTranslation } from 'react-i18next';

import { Column, ColumnType } from '@centreon/ui';

import { labelHosts, labelServices } from '../translatedLabels';
import { RowProps } from '../models';

import { Name } from './Name';
import Statuses from './Statuses/Statuses';

interface Props {
  groupType: string;
  groupTypeName: string;
  isFromPreview?: boolean;
}

export const useColumns = ({
  groupTypeName,
  groupType,
  isFromPreview
}: Props): Array<Column> => {
  const { t } = useTranslation();

  return [
    {
      Component: ({ row }: Pick<RowProps, 'row'>) => (
        <Name groupType={groupType} isFromPreview={isFromPreview} row={row} />
      ),
      align: 'start',
      clickable: true,
      id: 'name',
      label: t(groupTypeName),
      sortField: 'name',
      sortable: true,
      type: ColumnType.component,
      width: 'minmax(120px, auto)'
    },
    {
      Component: ({ row }: Pick<RowProps, 'row'>) => (
        <Statuses
          groupType={groupType}
          isFromPreview={isFromPreview}
          resourceType="host"
          row={row}
        />
      ),
      align: 'start',
      clickable: true,
      id: 'host',
      label: t(labelHosts),
      type: ColumnType.component,
      width: 'minmax(120px, 1fr)'
    },
    {
      Component: ({ row }: Pick<RowProps, 'row'>) => (
        <Statuses
          groupType={groupType}
          isFromPreview={isFromPreview}
          resourceType="service"
          row={row}
        />
      ),
      clickable: true,
      id: 'service',
      label: t(labelServices),
      type: ColumnType.component,
      width: 'minmax(230px, 1fr)'
    }
  ];
};
