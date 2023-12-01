import { useState } from 'react';

import { useFormikContext } from 'formik';
import { includes, pluck } from 'ramda';

import { ListItemText, MenuItem } from '@mui/material';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';

import { SelectEntry, buildListingEndpoint } from '@centreon/ui';

import { dashboardsEndpoint } from '../../../../api/endpoints';
import { PlaylistConfig } from '../../models';

interface UseSelectDashboardState {
  addDashboard: () => void;
  addIconDisabled: boolean;
  getEndpoint: (parameters) => string;
  getOptionDisabled: (option: SelectEntry) => boolean;
  renderOption: (attr, option: SelectEntry) => JSX.Element;
  selectDashboard: (_, entry: SelectEntry) => void;
  selectedDashboard: SelectEntry | null;
}

export const useSelectDashboard = (
  addItem: (item) => void
): UseSelectDashboardState => {
  const { values } = useFormikContext<PlaylistConfig>();

  const [selectedDashboard, setSelectedDashboard] =
    useState<SelectEntry | null>(null);

  const addIconDisabled = !selectedDashboard;

  const selectedDashboardIds = pluck('id', values.dashboards);

  const selectDashboard = (_, entry): void => {
    setSelectedDashboard(entry);
  };

  const addDashboard = (): void => {
    addItem(selectedDashboard);
    setSelectedDashboard(null);
  };

  const getEndpoint = (parameters): string =>
    buildListingEndpoint({
      baseEndpoint: dashboardsEndpoint,
      parameters: {
        ...parameters,
        sort: { name: 'ASC' }
      }
    });

  const renderOption = (attr, option): JSX.Element => {
    return (
      <MenuItem {...attr}>
        <ListItemText>{option.name}</ListItemText>
        {includes(option.id)(selectedDashboardIds) && (
          <CheckCircleIcon color="success" />
        )}
      </MenuItem>
    );
  };

  const getOptionDisabled = (option): boolean => {
    return includes(option.id)(selectedDashboardIds);
  };

  return {
    addDashboard,
    addIconDisabled,
    getEndpoint,
    getOptionDisabled,
    renderOption,
    selectDashboard,
    selectedDashboard
  };
};
