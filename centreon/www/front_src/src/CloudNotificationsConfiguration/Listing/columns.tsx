import { useTranslation } from 'react-i18next';

import { ColumnType, Column } from '@centreon/ui';

import {
  labelName,
  labelChannels,
  labelUsers,
  labelResources,
  labelPeriod,
  labelActions,
  labelStatus
} from '../translatedLabels';

import { FormatChannels, formatResourcesForListing } from './utils';
import { Actions, Activate } from './Actions';

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
      getFormattedString: ({ userCount }): string => `${userCount} users`,
      id: 'userCount',
      label: t(labelUsers),
      sortField: 'users',
      sortable: true,
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ resources }): string =>
        formatResourcesForListing(resources),
      id: 'resources',
      label: t(labelResources),
      sortField: 'resources',
      sortable: true,
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ timeperiod }): string => timeperiod?.name,
      id: 'timeperiod',
      label: t(labelPeriod),
      type: ColumnType.string
    },
    {
      Component: FormatChannels,
      disablePadding: false,
      id: 'channels',
      label: t(labelChannels),
      type: ColumnType.component
    },
    {
      Component: Actions,
      clickable: true,
      disablePadding: true,
      id: 'actions',
      label: t(labelActions),
      type: ColumnType.component
    },
    {
      Component: Activate,
      clickable: true,
      disablePadding: false,
      id: 'isActivated',
      label: t(labelStatus),
      sortField: 'is_activated',
      sortable: true,
      type: ColumnType.component
    }
  ];
};

export default useListingColumns;
