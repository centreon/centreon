import { atom } from 'jotai';
import { WidgetResourceType } from '../../../models';

export interface ResourceTypeToToggleRegexAtom {
  resourceType: WidgetResourceType;
  index: number;
}

export const resourceTypeToToggleRegexAtom =
  atom<ResourceTypeToToggleRegexAtom | null>(null);
