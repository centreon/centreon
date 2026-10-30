import { type Column, ColumnType, truncate } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import {
  labelAlias,
  labelLinkedServiceTemplates,
  labelName,
  labelTemplates
} from '../translatedLabels';
import Name from './Name';
import ParentTemplates from './ParentTemplates';

interface Props {
  columns: Array<Column>;
}

// `getFormattedString` is mandatory: DataCell falls back to an empty string,
// not to `row[id]`.
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
      disablePadding: false,
      getFormattedString: ({
        linkedServiceTemplatesCount
      }: Record<string, unknown>) => String(linkedServiceTemplatesCount),
      id: 'linked_service_templates_count',
      label: t(labelLinkedServiceTemplates),
      type: ColumnType.string
    },
    {
      Component: ParentTemplates,
      // The links navigate; the cell must not also open the row's panel.
      clickable: true,
      disablePadding: false,
      id: 'parent_templates',
      label: t(labelTemplates),
      type: ColumnType.component
    }
  ];

  return { columns };
};

export default useColumns;
