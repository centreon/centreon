import { ReactNode } from 'react';

import { PrimitiveAtom } from 'jotai';

import { Resource } from '../models';

import { CheckActionAtom } from './Resource/Check/checkAtoms';

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
  stateCheckActionAtom?: PrimitiveAtom<CheckActionAtom | null>;
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

export interface ExtraActionsInformation {
  arrayActions: MainActions['actions'] | SecondaryActions;
  key: Action;
}

export enum Type {
  medium = 'medium',
  small = 'small'
}
interface Arg {
  close: () => void;
}
export interface MoreSecondaryActions {
  renderMoreSecondaryActions?: (arg: Arg) => ReactNode;
}

export const smallWidth = 760;
export const mediumWidth = 1100;
