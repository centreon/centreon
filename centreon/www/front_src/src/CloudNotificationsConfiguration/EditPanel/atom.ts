import { atom } from 'jotai';

import { PanelMode } from './models';

export const panelModeAtom = atom<PanelMode>(PanelMode.Create);

export const editedNotificationIdAtom = atom<number | null>(null);
