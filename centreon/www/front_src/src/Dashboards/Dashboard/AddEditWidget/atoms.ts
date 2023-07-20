import { atom } from 'jotai';

import { Widget } from './models';

export const widgetFormInitialDataAtom = atom<Widget | null>(null);
