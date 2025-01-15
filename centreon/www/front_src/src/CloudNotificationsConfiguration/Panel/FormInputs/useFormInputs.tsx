/* eslint-disable hooks/sort */
import { useState } from 'react';

import { T, always, cond, gt, isEmpty, not } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box } from '@mui/material';
import { Variant } from '@mui/material/styles/createTypography';

import { Group, InputType } from '@centreon/ui';

import {
  labelBusinessViews,
  labelBusinessViewsEvents,
  labelContacts,
  labelEmailTemplateForTheNotificationMessage,
  labelHostGroups,
  labelNotificationChannels,
  labelNotificationSettings,
  labelSearchBusinessViews,
  labelSearchContacts,
  labelSearchHostGroups,
  labelSearchServiceGroups,
  labelSelectResourcesAndEvents,
  labelSelectTimePeriod,
  labelServiceGroups,
  labelSubject,
  labelTimePeriod
} from '../../translatedLabels';
import {
  availableTimePeriodsEndpoint,
  businessViewsEndpoint,
  hostsGroupsEndpoint,
  serviceGroupsEndpoint,
  usersEndpoint
} from '../api/endpoints';
import { hostEvents, serviceEvents } from '../utils';

import { EmailBody } from './Channel';
import { useStyles } from './Inputs.styles';
import TimePeriodTitle from './TimePeriodTitle';

const handleGridTemplate = cond([
  [gt(650), always('auto')],
  [gt(800), always('40% 60%')],
  [T, always('repeat(2, 1fr)')]
]);

interface UseFormInputsProps {
  isBamModuleInstalled?: boolean;
  panelWidth: number;
}

interface UseFormInputsState {
  basicFormGroups: Array<Group>;
  inputs;
}

const useFormInputs = ({
  panelWidth,
  isBamModuleInstalled
}: UseFormInputsProps): UseFormInputsState => {
  const [isExtraFieldHidden, setIsExtraFieldHidden] = useState(false);

  const { classes } = useStyles({ isExtraFieldHidden });
  const { t } = useTranslation();

  const titleAttributes = {
    classes: { root: classes.titleGroup },
    variant: 'subtitle1' as Variant
  };

  const translatedServiceEvents = serviceEvents.map((service) => t(service));
  const translatedHostEvents = hostEvents.map((host) => t(host));

  const basicFormGroups: Array<Group> = [
    {
      name: t(labelSelectResourcesAndEvents),
      order: 1,
      titleAttributes
    },
    {
      name: t(labelNotificationSettings),
      order: 2,
      titleAttributes
    }
  ];

  const inputs = [
    {
      additionalLabel: t(labelHostGroups),
      additionalLabelClassName: classes.additionalLabel,
      dataTestId: t(labelHostGroups),
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
            dataTestId: labelSearchHostGroups,
            disableSortedOptions: true,
            fieldName: 'hostGroups.ids',
            label: t(labelSearchHostGroups),
            required: true,
            type: InputType.MultiConnectedAutocomplete
          },
          {
            checkbox: {
              direction: 'horizontal',
              labelPlacement: 'top',
              options: translatedHostEvents
            },
            dataTestId: 'Host groups events',
            fieldName: 'hostGroups.events',
            getDisabled: (values) => isEmpty(values.hostGroups.ids),
            label: 'Host groups events',
            type: InputType.CheckboxGroup
          },
          {
            dataTestId: 'include Services',
            fieldName: 'hostGroups.extra.includeServices',
            getDisabled: (values) => isEmpty(values.hostGroups.ids),
            hideInput: (values): boolean => {
              setIsExtraFieldHidden(isEmpty(values.hostGroups.ids));

              return isEmpty(values.hostGroups.ids);
            },
            label: 'include Services',
            type: InputType.Checkbox
          },
          {
            checkbox: {
              direction: 'horizontal',
              labelPlacement: 'top',
              options: translatedServiceEvents
            },
            dataTestId: 'Extra events services',
            fieldName: 'hostGroups.extra.eventsServices',
            getDisabled: (values) =>
              not(values.hostGroups?.extra?.includeServices.checked),
            hideInput: (values) => isEmpty(values.hostGroups.ids),
            label: 'Events',
            type: InputType.CheckboxGroup
          }
        ],
        gridTemplateColumns: handleGridTemplate(panelWidth)
      },
      group: basicFormGroups[0].name,
      inputClassName: classes.hostInput,
      label: t(labelHostGroups),
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
            dataTestId: labelSearchServiceGroups,
            disableSortedOptions: true,
            fieldName: 'serviceGroups.ids',
            label: t(labelSearchServiceGroups),
            required: true,
            type: InputType.MultiConnectedAutocomplete
          },
          {
            checkbox: {
              direction: 'horizontal',
              labelPlacement: 'top',
              options: translatedServiceEvents
            },
            dataTestId: 'Service groups events',
            fieldName: 'serviceGroups.events',
            getDisabled: (values) => isEmpty(values.serviceGroups.ids),
            label: 'Service groups events',
            type: InputType.CheckboxGroup
          }
        ],
        gridTemplateColumns: handleGridTemplate(panelWidth)
      },
      group: basicFormGroups[0].name,
      inputClassName: classes.hostInput,
      label: t(labelServiceGroups),
      type: InputType.Grid
    },
    ...(isBamModuleInstalled
      ? [
          {
            additionalLabel: t(labelBusinessViews),
            additionalLabelClassName: classes.additionalLabel,

            fieldName: '',
            grid: {
              alignItems: 'center',
              columns: [
                {
                  connectedAutocomplete: {
                    additionalConditionParameters: [],
                    endpoint: businessViewsEndpoint
                  },
                  dataTestId: labelSearchBusinessViews,
                  disableSortedOptions: true,
                  fieldName: 'businessviews.ids',
                  label: t(labelSearchBusinessViews),
                  required: true,
                  type: InputType.MultiConnectedAutocomplete
                },
                {
                  checkbox: {
                    direction: 'horizontal',
                    labelPlacement: 'top',
                    options: translatedServiceEvents
                  },
                  dataTestId: labelBusinessViewsEvents,
                  fieldName: 'businessviews.events',
                  getDisabled: (values) => isEmpty(values.businessviews.ids),
                  label: t(labelBusinessViewsEvents),
                  type: InputType.CheckboxGroup
                }
              ],
              gridTemplateColumns: handleGridTemplate(panelWidth)
            },
            group: basicFormGroups[0].name,
            inputClassName: classes.hostInput,
            label: t(labelSearchBusinessViews),
            type: InputType.Grid
          }
        ]
      : []),
    {
      additionalLabel: <TimePeriodTitle />,
      additionalLabelClassName: classes.additionalLabel,
      connectedAutocomplete: {
        additionalConditionParameters: [],
        endpoint: availableTimePeriodsEndpoint
      },
      dataTestId: t(labelTimePeriod),
      fieldName: 'timeperiod',
      group: basicFormGroups[1].name,
      inputClassName: classes.input,
      label: t(labelSelectTimePeriod),
      required: true,
      type: InputType.SingleConnectedAutocomplete
    },
    {
      additionalLabel: t(labelNotificationChannels),
      additionalLabelClassName: classes.additionalLabel,
      fieldName: '',
      grid: {
        className: classes.channels,
        columns: [
          {
            checkbox: {
              direction: 'horizontal'
            },
            dataTestId: 'Email',
            fieldName: 'messages.channel',
            getDisabled: T,
            label: 'Email',
            type: InputType.Checkbox
          },
          {
            checkbox: {
              direction: 'horizontal'
            },
            dataTestId: 'SMS',
            fieldName: 'sms.channel',
            getDisabled: T,
            label: 'SMS',
            type: InputType.Checkbox
          },
          {
            checkbox: {
              direction: 'horizontal'
            },
            dataTestId: 'Slack',
            fieldName: 'slack.channel',
            getDisabled: T,
            label: 'Slack',
            type: InputType.Checkbox
          }
        ]
      },
      group: basicFormGroups[1].name,
      inputClassName: classes.input,
      type: InputType.Grid
    },

    {
      additionalLabel: t(labelContacts),
      additionalLabelClassName: classes.additionalLabel,
      fieldName: '',
      grid: {
        columns: [
          {
            connectedAutocomplete: {
              additionalConditionParameters: [],
              endpoint: usersEndpoint
            },
            dataTestId: 'Search contacts',
            fieldName: 'users',
            label: t(labelSearchContacts),
            required: true,
            type: InputType.MultiConnectedAutocomplete
          },
          {
            custom: {
              Component: Box
            },
            fieldName: '',
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
      fieldName: '',
      grid: {
        className: classes.grid,
        columns: [
          {
            custom: {
              Component: () => (
                <Box className={classes.emailTemplateTitle}>
                  {t(labelEmailTemplateForTheNotificationMessage)}
                </Box>
              )
            },
            fieldName: '',
            group: basicFormGroups[1].name,
            label: 'Email template',
            type: InputType.Custom
          },
          {
            fieldName: 'messages.subject',
            group: basicFormGroups[1].name,
            label: t(labelSubject),
            type: InputType.Text
          },
          {
            custom: {
              Component: EmailBody
            },
            fieldName: 'messages.message',
            group: basicFormGroups[1].name,
            label: 'Message',
            type: InputType.Custom
          }
        ],
        gridTemplateColumns: 'auto'
      },
      group: basicFormGroups[1].name,
      inputClassName: classes.input,
      label: t(labelNotificationChannels),
      type: InputType.Grid
    }
  ];

  return { basicFormGroups, inputs };
};

export default useFormInputs;
