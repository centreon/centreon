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
  timeperiod,
  t,
  isBamModuleInstalled
}: NotificationType & {
  isBamModuleInstalled?: boolean;
  t: TFunction;
}): object => ({
  contactgroups,
  hostGroups: formatResource({
    resourceType: ResourcesTypeEnum.HG,
    resources,
    t
  }),
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
    message: defaultEmailBody,
    subject: ''
  },
  sms: {
    channel: {
      Icon: SmsIcon,
      checked: false,
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
    channel: { Icon: EmailIcon, checked: true, label: ChannelsEnum.Email },
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
      Icon: SlackIcon,
      checked: false,
      label: ChannelsEnum.Slack
    },
    message: defaultEmailBody,
    subject: ''
  },
  sms: {
    channel: {
      Icon: SmsIcon,
      checked: false,
      label: ChannelsEnum.Sms
    },
    message: defaultEmailBody,
    subject: ''
  },
  timeperiod: null,
  users: [],
  ...getBVInitialValue(isBamModuleInstalled)
});
