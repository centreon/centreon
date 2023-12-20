import { Resource } from '../models';

export enum Action {
  Acknowledge = 'Acknowledge',
  Check = 'Check',
  Comment = 'Comment',
  Disacknowledge = 'Disacknowledge',
  Downtime = 'Downtime',
  ForcedCheck = 'ForcedCheck',
  SubmitStatus = 'SubmitStatus'
}

export interface ExtraRules {
  disabled?: boolean;
  permitted?: boolean;
}

export interface MainActionModel {
  action: Exclude<Action, Action.Comment | Action.SubmitStatus | Action.Check>;
  extraRules: ExtraRules | null;
}

export interface Success {
  msgForcedCheckCommandSent: string;
  msgLabelCheckCommandSent: string;
}

export interface ListOptions {
  descriptionCheck: string;
  descriptionForcedCheck: string;
}
export interface Data {
  listOptions: ListOptions;
  success: Success;
}

export interface CheckActionModel {
  action: Action.Check;
  data: Data;
  extraRules: ExtraRules | null;
}

export interface SecondaryActionModel {
  action: Exclude<Action, Action.Acknowledge | Action.Check | Action.Downtime>;
  extraRules: ExtraRules | null;
}

export type MainActions = Array<MainActionModel | CheckActionModel>;
export type SecondaryActions = Array<SecondaryActionModel>;

export interface ResourceActions {
  displayCondensed?: boolean;
  mainActions: MainActions;
  mainActionsStyle?: string;
  resources: Array<Resource>;
  secondaryActions: SecondaryActions;
  success: () => void;
}

export interface ExtraActionsInformation {
  arrayActions: MainActions | SecondaryActions;
  key: Action;
}
