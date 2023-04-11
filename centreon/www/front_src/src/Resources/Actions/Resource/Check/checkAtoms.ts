import { atom } from 'jotai';

interface CheckActionAtom {
  checked: boolean;
  forcedChecked: boolean;
}

export const forcedCheckInlineEndpointAtom = atom<string | ''>('');
export const checkActionAtom = atom<CheckActionAtom | null>(null);
