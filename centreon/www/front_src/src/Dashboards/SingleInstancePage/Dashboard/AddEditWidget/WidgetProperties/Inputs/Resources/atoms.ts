import { atom } from 'jotai';
import { WidgetResourceType } from '../../../models';

export interface ResourceTypeToToggleRegexAtom {
  resourceType: WidgetResourceType;
  index: number;
  isRegexMode: boolean;
}

export const resourceTypeToToggleRegexAtom =
  atom<ResourceTypeToToggleRegexAtom | null>(null);
