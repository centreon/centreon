export enum ResourcesTypeEnum {
  BV = 'businessview',
  HG = 'hostgroup',
  SG = 'servicegroup'
}
export enum ChannelsEnum {
  Mail = 'mail',
  Sms = 'sms'
}

export interface TimeperiodType {
  id: number;
  name: string;
}

export interface ResourcesType {
  count: number;
  type: ResourcesTypeEnum;
}

export interface NotificationsType {
  channels: Array<ChannelsEnum>;
  id: number;
  isActivated: boolean;
  name: string;
  resources: Array<ResourcesType>;
  timeperiod: TimeperiodType;
  userCount: number;
}

export interface MetaType {
  limit: number;
  page: number;
  search?: Record<string, unknown>;
  sort_by?: Record<string, unknown>;
  total: number;
}

export interface NotificationsListingType {
  meta: MetaType;
  result: Array<NotificationsType>;
}
