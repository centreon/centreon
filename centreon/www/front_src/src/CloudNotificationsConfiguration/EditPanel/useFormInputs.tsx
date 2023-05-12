import { cond, gt, always, T, isEmpty, not } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Group, InputType } from '@centreon/ui';

import {
  labelSelectResourcesAndEvents,
  labelSelectUsers,
  labelSelectTimePeriodChannelsAndPreview
} from '../translatedLabels';

import { hostEvents, serviceEvents } from './utils';
import { EmailBody, EmailPreview } from './Channel';
import {
  hostsGroupsEndpoint,
  serviceGroupsEndpoint,
  usersEndpoint
} from './api/endpoints';

const handleGridTemplate = cond([
  [gt(650), always('auto')],
  [gt(800), always('40% 60%')],
  [T, always('repeat(2, 1fr)')]
]);

interface Props {
  panelWidth: number;
}

const useFormInputs = ({ panelWidth }: Props): object => {
  const { t } = useTranslation();

  const basicFormGroups: Array<Group> = [
    {
      name: t(labelSelectResourcesAndEvents),
      order: 1
    },
    {
      name: t(labelSelectUsers),
      order: 2
    },
    {
      name: t(labelSelectTimePeriodChannelsAndPreview),
      order: 3
    }
  ];

  const inputs = [
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
            required: true,
            type: InputType.MultiConnectedAutocomplete
          },
          {
            checkbox: {
              labelPlacement: 'top',
              options: hostEvents,
              row: true
            },
            fieldName: 'hostGroups.events',
            getDisabled: (values) => isEmpty(values.hostGroups.ids),
            label: 'Events',
            type: InputType.MultiCheckbox
          },
          {
            fieldName: 'hostGroups.extra.includeServices',
            getDisabled: (values) => isEmpty(values.hostGroups.ids),
            hideInput: (values) => isEmpty(values.hostGroups.ids),
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
            getDisabled: (values) =>
              not(values.hostGroups?.extra?.includeServices.checked),
            hideInput: (values) => isEmpty(values.hostGroups.ids),
            label: 'Events',
            type: InputType.MultiCheckbox
          }
        ],
        gridTemplateColumns: handleGridTemplate(panelWidth)
      },
      group: basicFormGroups[0].name,
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
            required: true,
            type: InputType.MultiConnectedAutocomplete
          },
          {
            checkbox: {
              labelPlacement: 'top',
              options: serviceEvents,
              row: true
            },
            fieldName: 'serviceGroups.events',
            getDisabled: (values) => isEmpty(values.serviceGroups.ids),
            label: 'Events',
            type: InputType.MultiCheckbox
          }
        ],
        gridTemplateColumns: handleGridTemplate(panelWidth)
      },
      group: basicFormGroups[0].name,
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
    //   group: basicFormGroups[0].name,
    //   label: 'Resources and events',
    //   type: InputType.Grid
    // },
    {
      connectedAutocomplete: {
        additionalConditionParameters: [],
        endpoint: usersEndpoint
      },
      fieldName: 'users',
      group: basicFormGroups[1].name,
      label: 'Search users',
      required: true,
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
                  getDisabled: () => true,
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
                        getDisabled: () => true,
                        label: 'SMS',
                        type: InputType.Checkbox
                      },
                      {
                        checkbox: {
                          row: true
                        },
                        fieldName: 'messages.channel',
                        getDisabled: () => true,
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
      group: basicFormGroups[2].name,
      label: '',
      type: InputType.Grid
    }
  ];

  return { basicFormGroups, inputs };
};

export default useFormInputs;
