import { Token } from '../TokenListing/models';

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
