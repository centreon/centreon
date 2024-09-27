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

export interface SuccessCallback {
  onSuccessCheckAction?: () => void;
  onSuccessForcedCheckAction?: () => void;
}
export interface Data {
  listOptions: ListOptions;
  stateCheckActionAtom?;
  successCallback: SuccessCallback;
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

export interface MainActions {
  actions: Array<MainActionModel | CheckActionModel>;
  style?: string;
}
export type SecondaryActions = Array<SecondaryActionModel>;

export interface ResourceActions {
  displayCondensed?: boolean;
  mainActions: MainActions;
  resources: Array<Resource>;
  secondaryActions: SecondaryActions;
  success?: () => void;
}
