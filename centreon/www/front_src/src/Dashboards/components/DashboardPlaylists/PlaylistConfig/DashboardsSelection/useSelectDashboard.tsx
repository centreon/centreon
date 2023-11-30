import { useState } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { append, includes, pluck } from 'ramda';

import { ListItemText, MenuItem } from '@mui/material';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';

import { SelectEntry, buildListingEndpoint } from '@centreon/ui';

import { dashboardsEndpoint } from '../../../../api/endpoints';

export const useSelectDashboard = () => {
  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const [selectedDashboard, setSelectedDashboard] =
    useState<SelectEntry | null>(null);

  const addIconDisabled = !selectedDashboard;

  const selectedDashboardIds = pluck('id', values.dashboards);

  const selectDashboard = (_, entry): void => {
    setSelectedDashboard(entry);
  };

  const addDashboard = (): void => {
    setFieldValue(
      'dashboards',
      append(
        {
          id: (selectedDashboard as SelectEntry).id,
          name: (selectedDashboard as SelectEntry).name,
          order: selectedDashboardIds.length
        },
        values.dashboards
      )
    );
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
