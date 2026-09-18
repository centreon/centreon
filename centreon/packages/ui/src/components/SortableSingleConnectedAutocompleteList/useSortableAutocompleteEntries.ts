import { find, propEq } from 'ramda';

import type { SelectEntry } from '../../InputField/Select';
import type { DragEnd } from '../../SortableItems';
import {
  createSortableAutocompleteEntry,
  type SortableAutocompleteEntry
} from './models';

interface UseSortableAutocompleteEntriesParams {
  items: Array<SortableAutocompleteEntry>;
  onChange: (items: Array<SortableAutocompleteEntry>) => void;
}

interface UseSortableAutocompleteEntriesResult {
  addEntry: () => void;
  changeEntryValue: (id: string, value: SelectEntry | null) => void;
  removeEntry: (id: string) => void;
  reorderEntries: (dragEnd: DragEnd) => void;
}

export const useSortableAutocompleteEntries = ({
  items,
  onChange
}: UseSortableAutocompleteEntriesParams): UseSortableAutocompleteEntriesResult => {
  const changeEntryValue = (id: string, value: SelectEntry | null): void => {
    onChange(items.map((item) => (item.id === id ? { ...item, value } : item)));
  };

  const reorderEntries = ({ items: orderedIds }: DragEnd): void => {
    onChange(
      orderedIds.map(
        (id) => find(propEq(id, 'id'), items) as SortableAutocompleteEntry
      )
    );
  };

  const addEntry = (): void => {
    onChange([...items, createSortableAutocompleteEntry()]);
  };

  const removeEntry = (id: string): void => {
    onChange(items.filter((item) => item.id !== id));
  };

  return { addEntry, changeEntryValue, removeEntry, reorderEntries };
};
