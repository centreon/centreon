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

export interface AnchorElDuration {
  anchorEl: HTMLDivElement | null;
  setAnchorEl: Dispatch<SetStateAction<HTMLDivElement | null>>;
}

export interface OpenPicker {
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
}
