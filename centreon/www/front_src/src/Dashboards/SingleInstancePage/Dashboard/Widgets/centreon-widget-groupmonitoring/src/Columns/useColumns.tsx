import { Column, ColumnType } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { RowProps } from '../models';
import { labelHosts, labelServices } from '../translatedLabels';
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
      align: 'start',
      Component: ({ row }: Pick<RowProps, 'row'>) => (
        <Name groupType={groupType} isFromPreview={isFromPreview} row={row} />
      ),
      clickable: true,
      id: 'name',
      label: t(groupTypeName),
      sortable: true,
      sortField: 'name',
      type: ColumnType.component,
      width: 'minmax(120px, auto)'
    },
    {
      align: 'start',
      Component: ({ row }: Pick<RowProps, 'row'>) => (
        <Statuses
          groupType={groupType}
          isFromPreview={isFromPreview}
          resourceType="host"
          row={row}
        />
      ),
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
