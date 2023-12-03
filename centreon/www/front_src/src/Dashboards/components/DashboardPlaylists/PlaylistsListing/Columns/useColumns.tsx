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
  labelUpdate
} from '../translatedLabels';
import { useIsViewerUser } from '../hooks';

import ActivatePublicLink from './ActivatePublicLink';
import PublicLink from './PublicLink';
import Actions from './Actions';
import Share from './Share';
import Role from './Role';
import RotationTime from './RotationTime';
import Description from './Decription';

const useListingColumns = (): Array<Column> => {
  const { t } = useTranslation();
  const isViewer = useIsViewerUser();

  const columns = [
    {
      disablePadding: false,
      getFormattedString: ({ name }): string => name,
      id: 'name',
      label: t(labelName),
      sortField: 'name',
      sortable: true,
      type: ColumnType.string
    },
    ...(isViewer
      ? []
      : [
          {
            Component: Share,
            disablePadding: false,
            displaySubItemsCaret: true,
            id: 'shares',
            label: t(labelShares),
            type: ColumnType.component,
            width: 'max-content'
          },
          {
            Component: Role,
            disablePadding: false,
            id: 'role',
            label: t(labelRole),
            type: ColumnType.component
          }
        ]),
    ...(isViewer
      ? [
          {
            disablePadding: false,
            getFormattedString: ({ description }): string => description,
            id: 'description',
            label: t(labelDescription),
            type: ColumnType.string
          }
        ]
      : [
          {
            Component: Description,
            disablePadding: false,
            id: 'description',
            label: t(labelDescription),
            type: ColumnType.component
          }
        ]),
    {
      Component: RotationTime,
      disablePadding: false,
      id: 'rotation_time',
      label: t(labelRotationTime),
      type: ColumnType.component
    },
    {
      disablePadding: false,
      getFormattedString: ({ author }): string => author?.name,
      id: 'author',
      label: t(labelCreator),
      sortField: 'author',
      sortable: true,
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ createdAt }): string => createdAt?.slice(0, 10),
      id: 'created_at',
      label: t(labelCreationDate),
      sortField: 'created_at',
      sortable: true,
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ updatedAt }): string => updatedAt?.slice(0, 10),
      id: 'updated_at',
      label: t(labelUpdate),
      sortField: 'updated_at',
      sortable: true,
      type: ColumnType.string
    },
    ...(isViewer
      ? []
      : [
          {
            Component: Actions,
            clickable: true,
            disablePadding: false,
            id: 'actions',
            label: t(labelActions),
            type: ColumnType.component
          }
        ]),
    {
      Component: PublicLink,
      clickable: true,
      disablePadding: false,
      id: 'publicLink',
      label: t(labelPublicLink),
      type: ColumnType.component
    },
    ...(isViewer
      ? []
      : [
          {
            Component: ActivatePublicLink,
            clickable: true,
            disablePadding: false,
            id: 'isPublic',
            label: t(labelPrivatePublic),
            type: ColumnType.component
          }
        ])
  ];

  return columns;
};

export default useListingColumns;
