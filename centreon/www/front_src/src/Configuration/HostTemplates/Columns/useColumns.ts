import { type Column, ColumnType, truncate } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import Templates from '../../Hosts/Columns/Templates';
import {
  labelAlias,
  labelName,
  labelTemplates
} from '../../Hosts/translatedLabels';
import { labelLinkedServiceTemplatesCount } from '../translatedLabels';
import Name from './Name/Name';

interface Props {
  columns: Array<Column>;
}

// The legacy listing's columns, without the duplicate count it typed inline:
// the shared duplicate action stands for it.
const useColumns = (): Props => {
  const { t } = useTranslation();

  const columns: Array<Column> = [
    {
      Component: Name,
      disablePadding: false,
      id: 'name',
      label: t(labelName),
      sortable: true,
      sortField: 'name',
      type: ColumnType.component
    },
    {
      disablePadding: false,
      getFormattedString: ({ alias }: Record<string, unknown>) =>
        truncate({ content: (alias as string) ?? '', maxLength: 50 }),
      id: 'alias',
      label: t(labelAlias),
      sortable: true,
      sortField: 'alias',
      type: ColumnType.string
    },
    {
      // The parent templates, each a link, as on the hosts listing.
      Component: Templates,
      clickable: true,
      disablePadding: false,
      id: 'templates',
      label: t(labelTemplates),
      type: ColumnType.component
    },
    {
      disablePadding: false,
      getFormattedString: ({
        serviceTemplatesCount
      }: Record<string, unknown>) =>
        String((serviceTemplatesCount as number | null | undefined) ?? 0),
      id: 'serviceTemplatesCount',
      label: t(labelLinkedServiceTemplatesCount),
      type: ColumnType.string
    }
  ];

  return { columns };
};

export default useColumns;
