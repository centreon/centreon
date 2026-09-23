import type { DragEndEvent } from '@dnd-kit/core';
import { arrayMove } from '@dnd-kit/sortable';
import {
  type RefObject,
  useEffect,
  useLayoutEffect,
  useMemo,
  useRef
} from 'react';

// A value paired with the stable key used for React keys, drag & drop and focus
// management. Consumers only ever see plain values.
interface Entry<T> {
  id: string;
  value: T;
}

const generateEntryId = (): string =>
  `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;

const focusableSelector =
  'input:not([disabled]):not([type="hidden"]), textarea:not([disabled]), select:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])';

const getRowSelector = (id: string): string =>
  `[data-entry-id="${CSS.escape(id)}"]`;

interface Params<T> {
  addLabel: string;
  createValue: () => T;
  onChange: (values: Array<T>) => void;
  values: Array<T>;
}

interface Result<T> {
  addEntry: () => void;
  containerRef: RefObject<HTMLDivElement | null>;
  entries: Array<Entry<T>>;
  removeEntry: (id: string) => void;
  reorderEntries: (event: DragEndEvent) => void;
  updateEntry: (id: string, update: (value: T) => T) => void;
}

export const useSortableEntries = <T>({
  addLabel,
  createValue,
  onChange,
  values
}: Params<T>): Result<T> => {
  const containerRef = useRef<HTMLDivElement>(null);
  // Latest entries, updated as soon as a change is emitted so that several
  // changes fired before the parent re-renders compose instead of overwriting
  // each other.
  const latestEntriesRef = useRef<Array<Entry<T>>>([]);
  const pendingFocusSelectorRef = useRef<string | null>(null);

  // Ids are kept by position: after a change emitted here, the latest entries
  // are exactly the emitted ones, so every row keeps its id (and its DOM node)
  // even when the parent stores a copy of the values.
  const entries = useMemo(
    () =>
      values.map((value, index) => ({
        id: latestEntriesRef.current[index]?.id ?? generateEntryId(),
        value
      })),
    [values]
  );

  useLayoutEffect(() => {
    latestEntriesRef.current = entries;
  }, [entries]);

  useEffect(() => {
    const selector = pendingFocusSelectorRef.current;

    if (!selector) {
      return;
    }

    pendingFocusSelectorRef.current = null;
    containerRef.current?.querySelector<HTMLElement>(selector)?.focus();
  }, [entries]);

  const commit = (nextEntries: Array<Entry<T>>): void => {
    latestEntriesRef.current = nextEntries;
    onChange(nextEntries.map(({ value }) => value));
  };

  const updateEntry = (id: string, update: (value: T) => T): void =>
    commit(
      latestEntriesRef.current.map((entry) =>
        entry.id === id ? { ...entry, value: update(entry.value) } : entry
      )
    );

  const addEntry = (): void => {
    const entry = { id: generateEntryId(), value: createValue() };

    pendingFocusSelectorRef.current = `${getRowSelector(entry.id)} [data-row-fields] :is(${focusableSelector})`;
    commit([...latestEntriesRef.current, entry]);
  };

  const removeEntry = (id: string): void => {
    const currentEntries = latestEntriesRef.current;
    const index = currentEntries.findIndex((entry) => entry.id === id);
    // Keep keyboard users in place: focus the row that takes the removed one's
    // position, else the previous row, else the add button.
    const nextFocusedEntry =
      currentEntries[index + 1] ?? currentEntries[index - 1];

    pendingFocusSelectorRef.current = nextFocusedEntry
      ? `${getRowSelector(nextFocusedEntry.id)} [data-testid="delete-row"]`
      : `button[aria-label="${CSS.escape(addLabel)}"]`;
    commit(currentEntries.filter((entry) => entry.id !== id));
  };

  const reorderEntries = ({ active, over }: DragEndEvent): void => {
    if (!over || active.id === over.id) {
      return;
    }

    const currentEntries = latestEntriesRef.current;

    commit(
      arrayMove(
        currentEntries,
        currentEntries.findIndex(({ id }) => id === active.id),
        currentEntries.findIndex(({ id }) => id === over.id)
      )
    );
  };

  return {
    addEntry,
    containerRef,
    entries,
    removeEntry,
    reorderEntries,
    updateEntry
  };
};
