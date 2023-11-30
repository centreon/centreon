import { useTranslation } from 'react-i18next';

import { ColumnType, Column } from '@centreon/ui';

import {
  labelActions,
  labelCreationDate,
  labelCreator,
  labelDescription,
  labelName,
  labelPrivatePublic,
  labelPublicLink,
  labelRole,
  labelRotationTime,
  labelShares,
  labelUpdate,
  labelSeconds
} from '../translatedLabels';

import ActivatePublicLink from './ActivatePublicLink';
import PublicLink from './PublicLink';

const columns = [
  'Name',
  'shares',
  'role',
  'description',
  'rotation_time',
  'author',
  'updated_at',
  'created_at',
  'actions',
  'public_link',
  'is_public'
];

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
      id: 'shares',
      label: t(labelShares),
      type: ColumnType.string
    },
    {
      disablePadding: false,
      id: 'role',
      label: t(labelRole),
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ description }): string => description,
      id: 'description',
      label: t(labelDescription),
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ rotationTime }): string =>
        `${rotationTime} ${t(labelSeconds)}`,
      id: 'rotation_time',
      label: t(labelRotationTime),
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ author }): string => author.name,
      id: 'author',
      label: t(labelCreator),
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ createdAt }): string => createdAt.slice(0, 10),
      id: 'created_at',
      label: t(labelCreationDate),
      sortField: 'created_at',
      sortable: true,
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ updatedAt }): string => updatedAt.slice(0, 10),
      id: 'updated_at',
      label: t(labelUpdate),
      sortField: 'updated_at',
      sortable: true,
      type: ColumnType.string
    },
    {
      clickable: true,
      disablePadding: false,
      id: 'actions',
      label: t(labelActions),
      type: ColumnType.string
    },
    {
      Component: PublicLink,
      clickable: true,
      disablePadding: false,
      id: 'publicLink',
      label: t(labelPublicLink),
      type: ColumnType.component
    },
    {
      Component: ActivatePublicLink,
      clickable: true,
      disablePadding: false,
      id: 'isPublic',
      label: t(labelPrivatePublic),
      type: ColumnType.component
    }
  ];
};

export default useListingColumns;
