import { useTranslation } from 'react-i18next';
import { prop, map, pipe, reject, propEq } from 'ramda';

import { ColumnType, Column } from '@centreon/ui';

import {
  labelActions,
  labelCreationDate,
  labelCreator,
  labelDashbords,
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
import Actions from './Actions/Actions';
import Share from './Share';
import Role from './Role';
import RotationTime from './RotationTime';
import Description from './Decription';
import DashboardsCount from './DashboardsCount';
import Name from './Name';

const useListingColumns = (): {
  columns: Array<Column>;
  defaultColumnsIds: Array<string>;
} => {
  const { t } = useTranslation();
  const isViewer = useIsViewerUser();

  const columns = [
    {
      Component: Name,
      clickable: true,
      disablePadding: false,
      id: 'name',
      label: t(labelName),
      sortField: 'name',
      sortable: true,
      type: ColumnType.component
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
      Component: DashboardsCount,
      disablePadding: false,
      id: 'dashboards',
      label: t(labelDashbords),
      type: ColumnType.component
    },
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
      id: 'public_link',
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
            id: 'is_public',
            label: t(labelPrivatePublic),
            type: ColumnType.component
          }
        ])
  ];

  const defaultColumnsIds = pipe(
    reject(propEq('dashboards', 'id')),
    map(prop('id'))
  )(columns);

  return { columns, defaultColumnsIds };
};

export default useListingColumns;
