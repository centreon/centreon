import { Column, ColumnType } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import {
  labelActions,
  labelChannels,
  labelName,
  labelPeriod,
  labelResources,
  labelStatus,
  labelUsers
} from '../translatedLabels';
import { Actions, Activate } from './Actions';
import { FormatChannels, formatResourcesForListing } from './utils';

const useListingColumns = (): Array<Column> => {
  const { t } = useTranslation();

  return [
    {
      disablePadding: false,
      getFormattedString: ({ name }): string => name,
      id: 'name',
      label: t(labelName),
      sortable: true,
      sortField: 'name',
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ userCount }): string => `${userCount} users`,
      id: 'userCount',
      label: t(labelUsers),
      sortable: true,
      sortField: 'users',
      type: ColumnType.string
    },
    {
      disablePadding: false,
      getFormattedString: ({ resources }): string =>
        formatResourcesForListing(resources),
      id: 'resources',
      label: t(labelResources),
      sortable: true,
      sortField: 'resources',
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
      sortable: true,
      sortField: 'is_activated',
      type: ColumnType.component
    }
  ];
};

export default useListingColumns;
