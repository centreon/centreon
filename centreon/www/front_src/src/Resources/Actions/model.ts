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

export type MainActions = Array<
  Exclude<Action, Action.Comment | Action.SubmitStatus>
>;
export type SecondaryActions = Array<
  Exclude<Action, Action.Acknowledge | Action.Check | Action.Downtime>
>;

export interface ResourceActions {
  displayCondensed?: boolean;
  initialize: () => void;
  mainActions: MainActions;
  mainActionsStyle?: string;
  resources: Array<Resource>;
  secondaryActions: SecondaryActions;
}
