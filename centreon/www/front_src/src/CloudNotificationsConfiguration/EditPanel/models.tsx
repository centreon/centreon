import { SvgIconComponent } from '@mui/icons-material';

import { ResourcesTypeEnum, ChannelsEnum, TimeperiodType } from '../models';

export enum PanelMode {
  Create = 'create',
  Edit = 'edit'
}

export interface IconCheckBoxProps {
  Icon: SvgIconComponent;
  text: string;
}
export interface MultiIconCheckBoxProps {
  items: Array<IconCheckBoxProps>;
}

export enum EventsType {
  critical = 'crtitical',
  down = 'down',
  ok = 'ok',
  unkown = 'unkown',
  unreachable = 'unreachable',
  up = 'up',
  warning = 'warning'
}

export interface MessageType {
  channel: ChannelsEnum;
  message: string;
  subject: string;
}

export interface UserType {
  id: number;
  name: string;
}

export interface ResourceIdsType {
  id: number;
  name: string;
}

export interface ResourceExtraType {
  eventsServices: Array<EventsType>;
}

export interface ResourceType {
  events: Array<EventsType>;
  extra?: ResourceExtraType; // traité plus tard
  ids: Array<ResourceIdsType>;
  type: ResourcesTypeEnum;
}

export interface NotificationType {
  id: number;
  isActivated: boolean;
  messages: Array<MessageType>;
  name: string;
  resources: Array<ResourceType>;
  timeperiod: TimeperiodType;
  users: Array<UserType>;
}
