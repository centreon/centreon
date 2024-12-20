export interface ListSearch {
  array: Array<string>;
  field: string;
}

export interface Label {
  firstLabel: string;
  secondLabel: string;
}

export interface CheckedLabel {
  label: string;
  value: boolean;
}

export type CheckedValue = {
  defaultLabel: string;
} & CheckedLabel;
