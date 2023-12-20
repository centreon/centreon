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
  action: Exclude<Action, Action.Comment | Action.SubmitStatus>;
  extraRules: ExtraRules | null;
}

export interface SecondaryActionModel {
  action: Exclude<Action, Action.Acknowledge | Action.Check | Action.Downtime>;
  extraRules: ExtraRules | null;
}

export type MainActions = Array<MainActionModel>;
export type SecondaryActions = Array<SecondaryActionModel>;

export interface ResourceActions {
  displayCondensed?: boolean;
  initialize: () => void;
  mainActions: MainActions;
  mainActionsStyle?: string;
  resources: Array<Resource>;
  secondaryActions: SecondaryActions;
}

export interface ExtraActionsInformation {
  arrayActions: MainActions | SecondaryActions;
  key: Action;
}
