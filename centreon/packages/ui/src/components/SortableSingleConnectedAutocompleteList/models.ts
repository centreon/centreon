import type { ReactElement } from 'react';

import type { SelectEntry } from '../../InputField/Select';

export interface SortableAutocompleteEntry {
  id: string;
  value: SelectEntry | null;
}

export interface SortableAutocompleteAction {
  color?: string;
  icon: ReactElement;
  id: string;
  isDisabled?: (entry: SortableAutocompleteEntry, index: number) => boolean;
  label: string;
  onClick: (entry: SortableAutocompleteEntry, index: number) => void;
}

const generateSortableAutocompleteEntryId = (): string =>
  `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;

export const createSortableAutocompleteEntry =
  (): SortableAutocompleteEntry => ({
    id: generateSortableAutocompleteEntryId(),
    value: null
  });

export const getSortableAutocompleteEntryValues = (
  entries: Array<SortableAutocompleteEntry>
): Array<SelectEntry> =>
  entries
    .map((entry) => entry.value)
    .filter((value): value is SelectEntry => value !== null);
