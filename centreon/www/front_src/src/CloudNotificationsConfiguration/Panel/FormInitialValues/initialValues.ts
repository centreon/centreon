import { TFunction } from 'i18next';

import { ChannelsEnum, ResourcesTypeEnum } from '../../models';
import { labelIncludeServicesForTheseHosts } from '../../translatedLabels';
import { EmailIcon, SlackIcon, SmsIcon } from '../FormInputs/Channel/Icons';
import { NotificationType } from '../models';
import {
  defaultEmailBody,
  defaultEmailSubject,
  formatMessages,
  formatResource
} from '../utils';

interface FormatBV {
  isBamModuleInstalled: boolean;
  resources;
}
const formatBV = ({ isBamModuleInstalled, resources }: FormatBV): object => {
  if (!isBamModuleInstalled) {
    return {};
  }

  return {
    businessviews: formatResource({
      resources,
      resourceType: ResourcesTypeEnum.BV
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
  timeperiod,
  t,
  isBamModuleInstalled
}: NotificationType & {
  isBamModuleInstalled?: boolean;
  t: TFunction;
}): object => ({
  contactgroups,
  hostGroups: formatResource({
    resources,
    resourceType: ResourcesTypeEnum.HG,
    t
  }),
  isActivated,
  messages: formatMessages({ messages, messageType: ChannelsEnum.Email }),
  name,
  serviceGroups: formatResource({
    resources,
    resourceType: ResourcesTypeEnum.SG
  }),
  slack: {
    channel: {
      checked: false,
      Icon: SlackIcon,
      label: ChannelsEnum.Slack
    },
    message: defaultEmailBody,
    subject: ''
  },
  sms: {
    channel: {
      checked: false,
      Icon: SmsIcon,
      label: ChannelsEnum.Sms
    },
    message: defaultEmailBody,
    subject: ''
  },
  timeperiod: { id: timeperiod.id, name: timeperiod.name },
  users,
  ...formatBV({ isBamModuleInstalled: !!isBamModuleInstalled, resources })
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
    channel: { checked: true, Icon: EmailIcon, label: ChannelsEnum.Email },
    message: defaultEmailBody,
    subject: defaultEmailSubject
  },
  name: '',
  serviceGroups: {
    events: [],
    ids: [],
    type: ResourcesTypeEnum.SG
  },
  slack: {
    channel: {
      checked: false,
      Icon: SlackIcon,
      label: ChannelsEnum.Slack
    },
    message: defaultEmailBody,
    subject: ''
  },
  sms: {
    channel: {
      checked: false,
      Icon: SmsIcon,
      label: ChannelsEnum.Sms
    },
    message: defaultEmailBody,
    subject: ''
  },
  timeperiod: null,
  users: [],
  ...getBVInitialValue(isBamModuleInstalled)
});
