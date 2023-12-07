import { lte } from 'ramda';

import { InputProps, InputType } from '@centreon/ui';

import {
  labelDefineTheOrderOfDashboards,
  labelDescription,
  labelName,
  labelSelectDashboards
} from '../../../translatedLabels';

import RotationTime from './RotationTime';
import SortContent from './DasbhoardSort/SortContent';
import SelectDashboard from './DashboardsSelection/SelectDashboard';

export const inputs: Array<InputProps> = [
  {
    fieldName: 'name',
    group: '',
    label: labelName,
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
    fieldName: 'dashboards',
    group: '',
    label: '',
    list: {
      AddItem: SelectDashboard,
      SortContent,
      addItemLabel: labelSelectDashboards,
      itemProps: ['id', 'name', 'order'],
      sortLabel: labelDefineTheOrderOfDashboards
    },
    type: InputType.List
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
