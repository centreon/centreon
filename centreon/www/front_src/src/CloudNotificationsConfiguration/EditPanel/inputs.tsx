import { cond, gt, always, T } from 'ramda';

import { Group, InputProps, InputType } from '@centreon/ui';

import {
  hostsGroupsEndpoint,
  serviceGroupsEndpoint,
  usersEndpoint
} from './api/endpoints';
import EmailBody from './Channel/EmailBody';
import EmailPreview from './Channel/EmailPreview';
import { hostEvents, serviceEvents } from './utils';

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
        alignItems: 'center',
        columns: [
          {
            connectedAutocomplete: {
              additionalConditionParameters: [],
              endpoint: hostsGroupsEndpoint
            },
            fieldName: 'hostGroups.ids',
            label: 'Search host groups',
            type: InputType.MultiConnectedAutocomplete
          },
          {
            checkbox: {
              labelPlacement: 'top',
              options: hostEvents,
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
              options: serviceEvents,
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
        alignItems: 'center',
        columns: [
          {
            connectedAutocomplete: {
              additionalConditionParameters: [],
              endpoint: serviceGroupsEndpoint
            },
            fieldName: 'serviceGroups.ids',
            label: 'Search Service groups',
            type: InputType.MultiConnectedAutocomplete
          },
          {
            checkbox: {
              labelPlacement: 'top',
              options: serviceEvents,
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
    // {
    //   additionalLabel: 'Business views',
    //   fieldName: '',
    //   grid: {
    //     alignItems: 'center',
    //     columns: [
    //       {
    //         connectedAutocomplete: {
    //           additionalConditionParameters: [],
    //           endpoint: businessViewsEndpoint
    //         },
    //         fieldName: 'businessViews.ids',
    //         label: 'Search Business Views',
    //         type: InputType.MultiConnectedAutocomplete
    //       },
    //       {
    //         checkbox: {
    //           labelPlacement: 'top',
    //           options: ,
    //           row: true
    //         },
    //         fieldName: 'businessViews.events',
    //         label: 'Events',
    //         type: InputType.MultiCheckbox
    //       }
    //     ],
    //     gridTemplateColumns: handleGridTemplate(panelWidth)
    //   },
    //   group: 'Select resources and events',
    //   label: 'Resources and events',
    //   type: InputType.Grid
    // },
    {
      connectedAutocomplete: {
        additionalConditionParameters: [],
        endpoint: usersEndpoint
      },
      fieldName: 'users',
      group: 'Select users',
      label: 'Search users',
      type: InputType.MultiConnectedAutocomplete
    },
    {
      fieldName: '',
      grid: {
        columns: [
          {
            fieldName: '',
            grid: {
              columns: [
                {
                  additionalLabel: 'Time period',
                  fieldName: 'timeperiod',
                  label: 'Time period',
                  type: InputType.Checkbox
                },
                {
                  additionalLabel: 'Notification Channels',
                  fieldName: '',
                  grid: {
                    columns: [
                      {
                        checkbox: {
                          row: true
                        },
                        fieldName: 'messages.channel',
                        label: 'Email',
                        type: InputType.Checkbox
                      },
                      {
                        checkbox: {
                          row: true
                        },
                        fieldName: 'messages.channel',
                        label: 'SMS',
                        type: InputType.Checkbox
                      },
                      {
                        checkbox: {
                          row: true
                        },
                        fieldName: 'messages.channel',
                        label: 'Slack',
                        type: InputType.Checkbox
                      }
                    ]
                  },
                  label: 'Notification Channels',
                  type: InputType.Grid
                },
                {
                  fieldName: 'messages.subject',

                  label: 'Subject',
                  type: InputType.Text
                },
                {
                  custom: {
                    Component: EmailBody
                  },
                  fieldName: 'messages.message',
                  label: 'Content',
                  type: InputType.Custom
                }
              ],
              gridTemplateColumns: 'repeat(1, 1fr)'
            },
            label: '',
            type: InputType.Grid
          },
          {
            custom: {
              Component: EmailPreview
            },
            fieldName: 'preview',
            label: 'Preview',
            type: InputType.Custom
          }
        ],
        gridTemplateColumns: gt(650)(panelWidth)
          ? 'repeat(1, 1fr)'
          : 'repeat(2, 1fr)'
      },
      group: 'Select time period/ channels of notifications / preview',
      label: '',
      type: InputType.Grid
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
