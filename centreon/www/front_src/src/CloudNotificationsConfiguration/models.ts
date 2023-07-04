export enum ResourcesTypeEnum {
  BA = 'ba',
  HG = 'hostgroup',
  SG = 'servicegroup'
}
export enum ChannelsEnum {
  Email = 'Email',
  Slack = 'Slack',
  Sms = 'Sms'
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

export enum DeleteType {
  MultipleItems = 'Multiple',
  SingleItem = 'Single'
}

export interface DeleteNotificationType {
  id: number | Array<number> | null;
  name?: string;
  type: DeleteType;
}
