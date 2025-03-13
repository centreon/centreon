import { Dispatch, SetStateAction } from 'react';

import { SelectEntry } from '@centreon/ui';

import { Token } from '../Listing/models';

export enum UnitDate {
  Day = 'd',
  Hour = 'h',
  Minute = 'm',
  Month = 'M',
  Quarter = 'Q',
  Second = 's',
  Week = 'w',
  Year = 'y'
}
export interface Duration {
  id: string;
  name: string;
  unit: UnitDate | null;
  value: number | null;
}

export type CreatedToken = Token & { token: string };

export interface UseCreateTokenFormValues {
  duration: SelectEntry | null;
  token?: string;
  tokenName: string;
  user: SelectEntry | null;
  type: SelectEntry;
}

export const dataDuration: Array<Duration> = [
  { id: '7days', name: '7 days', unit: UnitDate.Day, value: 7 },
  { id: '30days', name: '30 days', unit: UnitDate.Day, value: 30 },
  { id: '60days', name: '60 days', unit: UnitDate.Day, value: 60 },
  { id: '90days', name: '90 days', unit: UnitDate.Day, value: 90 },
  { id: '1year', name: '1 year', unit: UnitDate.Year, value: 1 },
  { id: 'neverExpire', name: 'Never expire', unit: UnitDate.Year, value: null },
  { id: 'customize', name: 'Customize', unit: null, value: null }
];

export const maxDays = 90;

export interface AnchorElDuration {
  anchorEl: HTMLDivElement | null;
  setAnchorEl: Dispatch<SetStateAction<HTMLDivElement | null>>;
}

export interface OpenPicker {
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
}
