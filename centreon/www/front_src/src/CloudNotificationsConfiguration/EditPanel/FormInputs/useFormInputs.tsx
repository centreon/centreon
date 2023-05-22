/* eslint-disable hooks/sort */
import { useState } from 'react';

import { cond, gt, always, T, isEmpty, not } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box } from '@mui/material';

import { Group, InputType } from '@centreon/ui';

import {
  labelSelectResourcesAndEvents,
  labelSelectUsers,
  labelSelectTimePeriodChannelsAndPreview,
  labelEmailTemplateForTheNotificationMessage,
  labelSubject,
  labelNotificationChannels,
  labelHostGroups,
  labelServiceGroups,
  labelUsers,
  labelTimePeriod
} from '../../translatedLabels';
import { hostEvents, serviceEvents } from '../utils';
import { EmailBody } from '../Channel';
import {
  hostsGroupsEndpoint,
  serviceGroupsEndpoint,
  usersEndpoint
} from '../api/endpoints';

import { useStyles } from './Inputs.styles';

const handleGridTemplate = cond([
  [gt(650), always('auto')],
  [gt(800), always('40% 60%')],
  [T, always('repeat(2, 1fr)')]
]);

interface Props {
  panelWidth: number;
}

const useFormInputs = ({ panelWidth }: Props): object => {
  const [isExtraFieldHiden, setIsExtraFieldHiden] = useState(false);

  const { classes } = useStyles({ isExtraFieldHiden });
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
      additionalLabel: t(labelHostGroups),
      additionalLabelClassName: classes.additionalLabel,
      fieldName: 'hostGroups',
      grid: {
        alignItems: 'center',
        className: classes.hostsGrid,
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
            hideInput: (values): boolean => {
              setIsExtraFieldHiden(isEmpty(values.hostGroups.ids));

              return isEmpty(values.hostGroups.ids);
            },
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
      inputClassName: classes.hostInput,
      label: 'Resources and events',
      type: InputType.Grid
    },
    {
      additionalLabel: t(labelServiceGroups),
      additionalLabelClassName: classes.additionalLabel,
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
      inputClassName: classes.hostInput,
      label: 'Resources and events',
      type: InputType.Grid
    },
    {
      additionalLabel: t(labelUsers),
      additionalLabelClassName: classes.additionalLabel,
      fieldName: '',
      grid: {
        columns: [
          {
            connectedAutocomplete: {
              additionalConditionParameters: [],
              endpoint: usersEndpoint
            },
            fieldName: 'users',
            label: 'Search users',
            required: true,
            type: InputType.MultiConnectedAutocomplete
          },
          {
            custom: {
              Component: Box
            },
            fieldName: 'users',
            label: '',
            type: InputType.Custom
          }
        ],
        gridTemplateColumns: handleGridTemplate(panelWidth)
      },
      group: basicFormGroups[1].name,
      inputClassName: classes.input,
      label: '',
      type: InputType.Grid
    },
    {
      additionalLabel: t(labelTimePeriod),
      additionalLabelClassName: classes.additionalLabel,
      fieldName: 'timeperiod',
      getDisabled: () => true,
      group: basicFormGroups[2].name,
      inputClassName: classes.input,
      label: 'Time period',
      type: InputType.Checkbox
    },
    {
      additionalLabel: t(labelNotificationChannels),
      additionalLabelClassName: classes.additionalLabel,
      fieldName: '',
      grid: {
        className: classes.grid,
        columns: [
          {
            fieldName: '',
            grid: {
              className: classes.channels,
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
                  fieldName: 'sms.channel',
                  getDisabled: () => true,
                  label: 'SMS',
                  type: InputType.Checkbox
                },
                {
                  checkbox: {
                    row: true
                  },
                  fieldName: 'slack.channel',
                  getDisabled: () => true,
                  label: 'Slack',
                  type: InputType.Checkbox
                }
              ]
            },
            group: basicFormGroups[2].name,
            label: 'Notification Channels',
            type: InputType.Grid
          },
          {
            custom: {
              Component: () => <Box className={classes.divider} />
            },
            fieldName: '',
            group: basicFormGroups[2].name,
            label: '',
            type: InputType.Custom
          },
          {
            custom: {
              Component: () => (
                <Box className={classes.emailTemplateTitle}>
                  {t(labelEmailTemplateForTheNotificationMessage)}
                </Box>
              )
            },
            fieldName: '',
            group: basicFormGroups[2].name,
            label: 'Email template',
            type: InputType.Custom
          },
          {
            fieldName: 'messages.subject',
            group: basicFormGroups[2].name,
            label: t(labelSubject),
            type: InputType.Text
          },
          {
            custom: {
              Component: EmailBody
            },
            fieldName: 'messages.message',
            group: basicFormGroups[2].name,
            label: 'Content',
            type: InputType.Custom
          }
        ],
        gridTemplateColumns: 'auto'
      },
      group: basicFormGroups[2].name,
      inputClassName: classes.input,
      label: 'Notification channels',
      type: InputType.Grid
    }
  ];

  return { basicFormGroups, inputs };
};

export default useFormInputs;
