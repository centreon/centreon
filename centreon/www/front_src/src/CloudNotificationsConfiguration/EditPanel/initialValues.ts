import MailIcon from '@mui/icons-material/LocalPostOfficeOutlined';

import { ChannelsEnum, ResourcesTypeEnum } from '../models';

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
  // businessViews: formatResource({
  //   resourceType: ResourcesTypeEnum.BV,
  //   resources
  // }),
  hostGroups: formatResource({ resourceType: ResourcesTypeEnum.HG, resources }),
  isActivated,
  messages: formatMessages(messages[0]),
  name,
  serviceGroups: formatResource({
    resourceType: ResourcesTypeEnum.SG,
    resources
  }),
  timeperiod: formatEntityNamed(timeperiod),
  users
});

export const emptyInitialValues = {
  // businessViews: {
  //   events: [],
  //   ids: [],
  //   type: 'Business views'
  // },
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
    channel: { Icon: MailIcon, checked: false, label: ChannelsEnum.Email },
    message: emptyEmail,
    subject: ''
  },
  name: 'Notification #1',
  serviceGroups: {
    events: [],
    ids: [],
    type: 'Service groups'
  },
  timeperiod: { checked: true, label: '24h/24 - 7/7 days' },
  users: []
};
