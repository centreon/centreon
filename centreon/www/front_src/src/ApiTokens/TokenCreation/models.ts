import { PersonalInformation, Token } from '../TokenListing/models';

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
  unit: UnitDate;
  value: number;
}

export type CreatedToken = Token & { token: string };

export interface ParamsCreateToken {
  durationData: Duration;
  tokenNameData: string;
  userData: PersonalInformation;
}

export interface UseCreateTokenFormValues {
  durationValue: Omit<Duration, 'unit' | 'value'> | null;
  token: string | null;
  tokenNameValue: string;
}

export const dataDuration: Array<Duration> = [
  { id: '7days', name: '7 days', unit: UnitDate.Day, value: 7 },
  { id: '30days', name: '30 days', unit: UnitDate.Day, value: 30 },
  { id: '60days', name: '60 days', unit: UnitDate.Day, value: 60 },
  { id: '90days', name: '90 days', unit: UnitDate.Day, value: 90 },
  { id: 'oneyear', name: '1 year', unit: UnitDate.Year, value: 1 },
  { id: 'customize', name: 'Customize', unit: null, value: null }
];

export const maxDays = 90;
