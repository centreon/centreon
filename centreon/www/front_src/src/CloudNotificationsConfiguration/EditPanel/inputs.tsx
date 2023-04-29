import { cond, gt, always, T } from 'ramda';

import { Group, InputProps, InputType } from '@centreon/ui';

const hostGroupEvents = ['up', 'down', 'unreachable'];

const servicesEvents = ['ok', 'warning', 'crtitical', 'unkown'];

const handleGridTemplate = cond([
  [gt(650), always('auto')],
  [gt(800), always('40% 60%')],
  [T, always('repeat(2, 1fr)')]
]);

export const getInputs = ({
  panelWidth
}: {
  panelWidth: number;
}): Array<InputProps> => {
  return [
    {
      additionalLabel: 'Host groups',
      fieldName: 'hostGroups',
      grid: {
        columns: [
          {
            autocomplete: {
              options: []
            },
            fieldName: 'hostGroups.ids',
            label: 'Search host groups',
            type: InputType.MultiAutocomplete
          },
          {
            checkbox: {
              labelPlacement: 'top',
              options: hostGroupEvents,
              row: true
            },
            fieldName: 'hostGroups.events',
            label: 'Events',
            type: InputType.MultiCheckbox
          },
          {
            fieldName: 'hostGroups.extra.includeServices',
            label: 'Events',
            type: InputType.Checkbox
          },
          {
            checkbox: {
              labelPlacement: 'top',
              options: servicesEvents,
              row: true
            },
            fieldName: 'hostGroups.extra.eventsServices',
            label: 'Events',
            type: InputType.MultiCheckbox
          }
        ],
        gridTemplateColumns: handleGridTemplate(panelWidth)
      },
      group: 'Select resources and events',
      label: 'Resources and events',
      type: InputType.Grid
    },
    {
      additionalLabel: 'Service groups',
      fieldName: '',
      grid: {
        columns: [
          {
            autocomplete: {
              options: []
            },
            fieldName: 'serviceGroups.ids',
            label: 'Search Service groups',
            type: InputType.MultiAutocomplete
          },
          {
            checkbox: {
              labelPlacement: 'top',
              options: servicesEvents,
              row: true
            },
            fieldName: 'serviceGroups.events',
            label: 'Events',
            type: InputType.MultiCheckbox
          }
        ],
        gridTemplateColumns: handleGridTemplate(panelWidth)
      },
      group: 'Select resources and events',
      label: 'Resources and events',
      type: InputType.Grid
    },
    {
      additionalLabel: 'Business views',
      fieldName: '',
      grid: {
        columns: [
          {
            autocomplete: {
              options: []
            },
            fieldName: 'businessViews.ids',
            label: 'Search Business Views',
            type: InputType.MultiAutocomplete
          },
          {
            checkbox: {
              labelPlacement: 'top',
              options: servicesEvents,
              row: true
            },
            fieldName: 'businessViews.events',
            label: 'Events',
            type: InputType.MultiCheckbox
          }
        ],
        gridTemplateColumns: handleGridTemplate(panelWidth)
      },
      group: 'Select resources and events',
      label: 'Resources and events',
      type: InputType.Grid
    },
    {
      // additionalLabel: 'Time period',
      fieldName: 'timeperiod',
      group: 'Select time period/ channels of notifications / preview',
      label: 'Time period',
      type: InputType.Checkbox
    },
    {
      checkbox: {
        row: true
      },
      fieldName: 'messages.channel',
      group: 'Select time period/ channels of notifications / preview',
      label: 'Channel',
      type: InputType.Checkbox
    },
    {
      fieldName: 'messages.subject',
      group: 'Select time period/ channels of notifications / preview',
      label: 'Subject',
      type: InputType.Text
    },
    {
      autocomplete: {
        options: []
      },
      fieldName: 'users',
      group: 'Select users',
      label: 'Search users',
      type: InputType.MultiAutocomplete
    }
  ];
};

export const basicFormGroups: Array<Group> = [
  {
    name: 'Select resources and events',
    order: 1
  },
  {
    name: 'Select users',
    order: 2
  },
  {
    name: 'Select time period/ channels of notifications / preview',
    order: 3
  }
];
