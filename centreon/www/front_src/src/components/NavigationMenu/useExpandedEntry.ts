import { find } from 'ramda';
import { useState } from 'react';

import type { RouteHierarchy } from './models';
import { containsRoute, getEntryKey } from './utils';

interface ExpandedEntry {
  expandedKey?: string;
  toggle: (key: string) => void;
}

/**
 * Only one of the given sibling entries is expanded at a time. The one that
 * contains the selected route is expanded first, and again whenever the
 * selected route moves to another of them.
 */
export const useExpandedEntry = (
  entries: Array<RouteHierarchy>,
  selectedRoute?: string
): ExpandedEntry => {
  const selectedEntry = find(
    (entry) => containsRoute(entry, selectedRoute),
    entries
  );
  const selectedKey = selectedEntry ? getEntryKey(selectedEntry) : undefined;

  const [expandedKey, setExpandedKey] = useState(selectedKey);
  const [syncedKey, setSyncedKey] = useState(selectedKey);

  if (selectedKey !== syncedKey) {
    setSyncedKey(selectedKey);

    if (selectedKey) {
      setExpandedKey(selectedKey);
    }
  }

  const toggle = (key: string): void =>
    setExpandedKey((currentKey) => (currentKey === key ? undefined : key));

  return { expandedKey, toggle };
};
