import { type Column, ColumnType } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import {
  labelAction,
  labelAuthor,
  labelDate,
  labelObject,
  labelObjectType
} from '../translatedLabels';
import ActionColumn from './Columns/ActionColumn';
import AuthorColumn from './Columns/AuthorColumn';
import DateColumn from './Columns/DateColumn';
import ExpandColumn from './Columns/ExpandColumn';
import ObjectColumn from './Columns/ObjectColumn';
import TypeColumn from './Columns/TypeColumn';

export const useColumns = (): Array<Column> => {
  const { t } = useTranslation();

  const objectColumns: Array<Column> = [
    {
      Component: TypeColumn,
      id: 'target_type',
      label: t(labelObjectType),
      type: ColumnType.component,
      width: 'minmax(120px, 0.5fr)'
    },
    {
      Component: ObjectColumn,
      id: 'target_name',
      label: t(labelObject),
      type: ColumnType.component,
      width: 'minmax(180px, 1fr)'
    }
  ];

  return [
    {
      Component: ExpandColumn,
      clickable: true,
      id: 'expand',
      label: '',
      type: ColumnType.component,
      width: '40px'
    },
    {
      Component: DateColumn,
      id: 'date',
      label: t(labelDate),
      sortable: true,
      type: ColumnType.component,
      width: 'minmax(140px, 0.5fr)'
    },
    {
      Component: ActionColumn,
      clickable: true,
      id: 'action',
      label: t(labelAction),
      type: ColumnType.component,
      width: 'max-content'
    },
    ...objectColumns,
    {
      Component: AuthorColumn,
      clickable: true,
      id: 'author',
      label: t(labelAuthor),
      type: ColumnType.component,
      width: 'minmax(140px, 0.5fr)'
    }
  ];
};
