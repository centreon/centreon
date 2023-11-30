import { lte } from 'ramda';

import { InputProps, InputType } from '@centreon/ui';

import { labelDescription, labelPlaylistName } from '../../../translatedLabels';

import DashboardsSelection from './DashboardsSelection/DashboardsSelection';
import RotationTime from './RotationTime';

export const inputs: Array<InputProps> = [
  {
    fieldName: 'name',
    group: '',
    label: labelPlaylistName,
    type: InputType.Text
  },
  {
    fieldName: 'description',
    group: '',
    label: labelDescription,
    text: {
      multilineRows: 3
    },
    type: InputType.Text
  },
  {
    custom: {
      Component: DashboardsSelection
    },
    fieldName: 'dashboards',
    group: '',
    label: '',
    type: InputType.Custom
  },
  {
    custom: {
      Component: RotationTime
    },
    fieldName: 'rotationTime',
    getDisabled: ({ dashboards }) => lte(dashboards.length, 1),
    group: '',
    label: '',
    type: InputType.Custom
  }
];
