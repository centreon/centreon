import { TFunction } from 'i18next';

import { ChannelsEnum, ResourcesTypeEnum } from '../../models';
import {
  labelIncludeServicesForTheseHosts,
  labelTimePeriod24h7days
} from '../../translatedLabels';
import { SlackIcon, EmailIcon, SmsIcon } from '../FormInputs/Channel/Icons';
import { NotificationType } from '../models';
import { emptyEmail, formatMessages, formatResource } from '../utils';

const formatBV = (isBamModuleInstalled, resources): object => {
  if (!isBamModuleInstalled) {
    return {};
  }

  return {
    businessviews: formatResource({
      resourceType: ResourcesTypeEnum.BV,
      resources
    })
  };
};

export const getInitialValues = ({
  name,
  isActivated,
  users,
  messages,
  resources,
  contactgroups,
  t,
  isBamModuleInstalled
}: NotificationType & {
  isBamModuleInstalled?: boolean;
  t: TFunction;
}): object => ({
  contactgroups,
  hostGroups: formatResource({ resourceType: ResourcesTypeEnum.HG, resources }),
  isActivated,
  messages: formatMessages({ messageType: ChannelsEnum.Email, messages }),
  name,
  serviceGroups: formatResource({
    resourceType: ResourcesTypeEnum.SG,
    resources
  }),
  slack: {
    channel: {
      Icon: SlackIcon,
      checked: false,
      label: ChannelsEnum.Slack
    },
    message: emptyEmail,
    subject: ''
  },
  sms: {
    channel: {
      Icon: SmsIcon,
      checked: false,
      label: ChannelsEnum.Sms
    },
    message: emptyEmail,
    subject: ''
  },
  timeperiod: {
    checked: true,
    label: t(labelTimePeriod24h7days)
  },
  users,
  ...formatBV(!!isBamModuleInstalled, resources)
});

const getBVInitialValue = (isBamModuleInstalled): object => {
  if (!isBamModuleInstalled) {
    return {};
  }

  return {
    businessviews: {
      events: [],
      ids: [],
      type: ResourcesTypeEnum.BV
    }
  };
};

export const getEmptyInitialValues = ({
  t,
  isBamModuleInstalled
}: {
  isBamModuleInstalled?: boolean;
  t: TFunction;
}): object => ({
  contactgroups: [],
  hostGroups: {
    events: [],
    extra: {
      eventsServices: [],
      includeServices: {
        checked: false,
        label: t(labelIncludeServicesForTheseHosts)
      }
    },
    ids: [],
    type: ResourcesTypeEnum.HG
  },
  isActivated: true,
  messages: {
    channel: { Icon: EmailIcon, checked: true, label: ChannelsEnum.Email },
    message: emptyEmail,
    subject: ''
  },
  name: '',
  serviceGroups: {
    events: [],
    ids: [],
    type: ResourcesTypeEnum.SG
  },
  slack: {
    channel: {
      Icon: SlackIcon,
      checked: false,
      label: ChannelsEnum.Slack
    },
    message: emptyEmail,
    subject: ''
  },
  sms: {
    channel: {
      Icon: SmsIcon,
      checked: false,
      label: ChannelsEnum.Sms
    },
    message: emptyEmail,
    subject: ''
  },
  timeperiod: { checked: true, label: t(labelTimePeriod24h7days) },
  users: [],
  ...getBVInitialValue(isBamModuleInstalled)
});
