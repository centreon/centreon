import { atom } from 'jotai';

import { PanelMode } from './models';

export const notificationNameAtom = atom<string>('');

export const panelModeAtom = atom<PanelMode>(PanelMode.Create);

export const EditedNotificationIdAtom = atom<number | null>(null);
