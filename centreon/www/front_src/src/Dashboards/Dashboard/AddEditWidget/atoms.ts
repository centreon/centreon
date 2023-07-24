import { atom } from 'jotai';

import { Widget } from './models';
import { WidgetPropertiesRenderer } from './WidgetProperties/useWidgetProperties';

export const widgetFormInitialDataAtom = atom<Widget | null>(null);

export const widgetPropertiesAtom =
  atom<Array<WidgetPropertiesRenderer> | null>(null);
