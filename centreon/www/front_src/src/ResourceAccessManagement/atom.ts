import { atom } from 'jotai';

import { ResourceAccessRuleType } from './models';

type SortOrder = 'asc' | 'desc';

export const limitAtom = atom<number | undefined>(10);
export const pageAtom = atom<number | undefined>(undefined);
export const searchAtom = atom<string>('');
export const sortOrderAtom = atom<SortOrder>('asc');
export const sortFieldAtom = atom<string>('name');

export const selectedRowsAtom = atom<Array<ResourceAccessRuleType>>([]);
export const resourceAccessRulesNamesAtom = atom<
  Array<{ id: number; name: string }>
>([]);
