import { ChannelsEnum, ResourcesTypeEnum } from '../models';

import { SlackIcon, EmailIcon, SmsIcon } from './Channel/Icons';
import { NotificationType } from './models';
import {
  emptyEmail,
  formatEntityNamed,
  formatMessages,
  formatResource
} from './utils';

export const getInitialValues = ({
  name,
  isActivated,
  users,
  timeperiod,
  messages,
  resources
}: NotificationType): object => ({
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
  timeperiod: formatEntityNamed(timeperiod),
  users
});

export const emptyInitialValues = {
  hostGroups: {
    events: [],
    extra: {
      eventsServices: [],
      includeServices: {
        checked: false,
        label: 'Include services for these hosts'
      }
    },
    ids: [],
    type: 'Host groups'
  },
  isActivated: true,
  messages: {
    channel: { Icon: EmailIcon, checked: true, label: ChannelsEnum.Email },
    message: emptyEmail,
    subject: ''
  },
  name: 'Notification #1',
  serviceGroups: {
    events: [],
    ids: [],
    type: 'Service groups'
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
  timeperiod: { checked: true, label: '24h/24 - 7/7 days' },
  users: []
};
