import { atom } from 'jotai';

import { SelectedWidget, Widget } from './models';
import { WidgetPropertiesRenderer } from './WidgetProperties/useWidgetInputs';

export const widgetFormInitialDataAtom = atom<Widget | null>(null);

export const widgetPropertiesAtom =
  atom<Array<WidgetPropertiesRenderer> | null>(null);

export const singleMetricSelectionAtom = atom<boolean | undefined>(undefined);

export const singleResourceTypeSelectionAtom = atom<boolean | undefined>(
  undefined
);

export const customBaseColorAtom = atom<boolean | undefined>(undefined);

export const metricsOnlyAtom = atom<boolean | undefined>(undefined);

export const selectedWidgetAtom = atom<SelectedWidget | undefined>(undefined);
