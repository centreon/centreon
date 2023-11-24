import { atom } from 'jotai';

import { ResourceAccessRuleType } from './models';

export const resourceAccessManagementSearchAtom = atom<string>('');

export const selectedRowsAtom = atom<Array<ResourceAccessRuleType>>([]);
export const resourceAccessRulesNamesAtom = atom<
  Array<{ id: number; name: string }>
>([]);
