/* eslint-disable @typescript-eslint/no-duplicate-enum-values */

import { SvgIconComponent } from '@mui/icons-material';

import { ChannelsEnum, ResourcesTypeEnum, TimeperiodType } from '../models';

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
  Critical = 'Critical',
  Down = 'Down',
  Ok = 'Recovery',
  Unknown = 'Unknown',
  Unreachable = 'Unreachable',
  Up = 'Recovery',
  Warning = 'Warning'
}

export interface MessageType {
  channel: ChannelsEnum;
  formattedMessage: string;
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
  eventsServices: number;
}

export interface ResourceType {
  events: number;
  extra?: ResourceExtraType;
  ids: Array<ResourceIdsType>;
  type: ResourcesTypeEnum;
}

export interface NotificationType {
  contactgroups: Array<UserType>;
  id: number;
  isActivated: boolean;
  messages: Array<MessageType>;
  name: string;
  resources: Array<ResourceType>;
  timeperiod: TimeperiodType;
  users: Array<UserType>;
}
