import { cond, gt, always, T, isEmpty, not, equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Box } from '@mui/material';

import { Group, InputType } from '@centreon/ui';
import { ThemeMode } from '@centreon/ui-context';

import {
  labelSelectResourcesAndEvents,
  labelSelectUsers,
  labelSelectTimePeriodChannelsAndPreview,
  labelEmailTemplateForTheNotificationMessage
} from '../translatedLabels';

import { hostEvents, serviceEvents } from './utils';
import { EmailBody } from './Channel';
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

const useStyles = makeStyles()((theme) => ({
  additionalLabel: {
    color: theme.palette.primary.main,
    fontSize: theme.typography.h6.fontSize,
    fontweight: theme.typography.fontWeightMedium,
    marginBottom: theme.spacing(1),
    marginTop: theme.spacing(1)
  },
  channels: {
    paddingBottom: theme.spacing(1),
    paddingTop: theme.spacing(3)
  },
  divider: {
    background: theme.palette.divider,
    height: theme.spacing(0.125)
  },
  emailTemplateTitle: {
    fontWeight: theme.typography.fontWeightBold
  },
  grid: {
    '& > div:nth-child(3)': {
      marginTop: theme.spacing(4)
    },
    rowGap: theme.spacing(1)
  },
  input: {
    backgroundColor: equals(ThemeMode.light, theme.palette.mode)
      ? theme.palette.background.panelGroups
      : 'default',
    padding: theme.spacing(1)
  }
}));

const useFormInputs = ({ panelWidth }: Props): object => {
  const { classes } = useStyles();
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
      additionalLabelClassName: classes.additionalLabel,
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
      inputClassName: classes.input,
      label: 'Resources and events',
      type: InputType.Grid
    },
    {
      additionalLabel: 'Service groups',
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
      inputClassName: classes.input,
      label: 'Resources and events',
      type: InputType.Grid
    },
    {
      additionalLabel: 'Users',
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
      additionalLabel: 'Time period',
      additionalLabelClassName: classes.additionalLabel,
      fieldName: 'timeperiod',
      getDisabled: () => true,
      group: basicFormGroups[2].name,
      inputClassName: classes.input,
      label: 'Time period',
      type: InputType.Checkbox
    },
    {
      additionalLabel: 'Notification channels',
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
            label: 'email template',
            type: InputType.Custom
          },
          {
            fieldName: 'messages.subject',
            group: basicFormGroups[2].name,
            label: 'Subject',
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
