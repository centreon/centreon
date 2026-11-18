import { type RefObject, useLayoutEffect, useRef, useState } from 'react';

interface Result {
  hasHiddenRowsBelow: boolean;
  listRef: RefObject<HTMLUListElement | null>;
  maxHeight?: number;
  scrollRef: RefObject<HTMLDivElement | null>;
  updateHiddenRowsBelow: () => void;
}

// Caps the list height to `maxVisibleRows` rows, measured from the rendered
// rows so it stays right whatever the row height (content, container width).
export const useMaxVisibleRows = (maxVisibleRows?: number): Result => {
  const scrollRef = useRef<HTMLDivElement>(null);
  const listRef = useRef<HTMLUListElement>(null);
  const [maxHeight, setMaxHeight] = useState<number>();
  const [hasHiddenRowsBelow, setHasHiddenRowsBelow] = useState(false);

  const updateHiddenRowsBelow = (): void => {
    const container = scrollRef.current;

    setHasHiddenRowsBelow(
      !!container &&
        container.scrollHeight - container.scrollTop - container.clientHeight >
          1
    );
  };

  useLayoutEffect(() => {
    const list = listRef.current;

    if (!maxVisibleRows || !list) {
      setMaxHeight(undefined);

      return undefined;
    }

    const observer = new ResizeObserver(() => {
      const cutOffRow = list.children[maxVisibleRows] as
        | HTMLElement
        | undefined;

      // Half of the first row past the cap stays visible, so the cut reads as
      // a deliberate hint that the list scrolls.
      setMaxHeight(
        cutOffRow
          ? Math.round(cutOffRow.offsetTop + cutOffRow.offsetHeight / 2)
          : undefined
      );
      updateHiddenRowsBelow();
    });

    observer.observe(list);

    return () => observer.disconnect();
  }, [maxVisibleRows]);

  useLayoutEffect(() => {
    // The cap can apply after a row was added and focused (e.g. the 11th row
    // of a list capped to 10): bring the focused row back into view.
    const focusedRow = document.activeElement?.closest('li');

    if (focusedRow && scrollRef.current?.contains(focusedRow)) {
      focusedRow.scrollIntoView({ block: 'nearest' });
    }

    updateHiddenRowsBelow();
  }, [maxHeight]);

  return {
    hasHiddenRowsBelow,
    listRef,
    maxHeight,
    scrollRef,
    updateHiddenRowsBelow
  };
};
