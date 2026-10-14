import {
  type Announcements,
  closestCenter,
  DndContext,
  type DragEndEvent,
  KeyboardSensor,
  type Modifier,
  PointerSensor,
  type UniqueIdentifier,
  useSensor,
  useSensors
} from '@dnd-kit/core';
import {
  SortableContext,
  sortableKeyboardCoordinates,
  verticalListSortingStrategy
} from '@dnd-kit/sortable';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

import { ItemComposition } from '../ItemComposition';
import type { RenderRowParams, RowParams, SortableRowAction } from './models';
import { Row } from './Row';
import {
  labelAdd,
  labelRowDragCancelled,
  labelRowDragInstructions,
  labelRowDropped,
  labelRowMovedOver,
  labelRowPickedUp
} from './translatedLabels';
import { useMaxVisibleRows } from './useMaxVisibleRows';
import { useSortableEntries } from './useSortableEntries';

export interface Props<T> {
  /** Extra per-row buttons, rendered before the delete button. */
  actions?: (params: RowParams<T>) => Array<SortableRowAction>;
  addLabel?: string;
  /** Initial value of a row added with the add button. */
  createValue: () => T;
  /** Shows the drag handle (last in the row) and enables reordering. */
  draggable?: boolean;
  getRowClassName?: (params: RowParams<T>) => string | undefined;
  /** Accessible name of the list. */
  label?: string;
  /**
   * Caps the list to this many rows, the next one half visible, and scrolls
   * the rest. The add button stays outside the scroll area.
   */
  maxVisibleRows?: number;
  onChange: (values: Array<T>) => void;
  /**
   * Renders the inputs of one row. `values` gives access to sibling rows. The
   * list is a CSS container, so the row layout can adapt to the list width
   * with container query classes (e.g. `@max-[600px]:grid-cols-1`).
   */
  renderRow: (params: RenderRowParams<T>) => ReactNode;
  values: Array<T>;
}

const restrictToVerticalAxis: Modifier = ({ transform }) => ({
  ...transform,
  x: 0
});

export const SortableEntriesList = <T,>({
  actions,
  addLabel,
  createValue,
  draggable = true,
  getRowClassName,
  label,
  maxVisibleRows,
  onChange,
  renderRow,
  values
}: Props<T>): JSX.Element => {
  const { t } = useTranslation();
  const translatedAddLabel = addLabel || t(labelAdd);

  const {
    addEntry,
    containerRef,
    entries,
    removeEntry,
    reorderEntries,
    updateEntry
  } = useSortableEntries({
    addLabel: translatedAddLabel,
    createValue,
    onChange,
    values
  });

  const {
    hasHiddenRowsBelow,
    listRef,
    maxHeight,
    scrollRef,
    updateHiddenRowsBelow
  } = useMaxVisibleRows(maxVisibleRows);

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
      // Instant scroll keeps each key press based on up-to-date positions in a
      // capped list.
      scrollBehavior: 'auto'
    })
  );

  const entryIds = entries.map(({ id }) => id);

  const getPosition = (id: UniqueIdentifier): number =>
    entryIds.indexOf(id as string) + 1;

  const announceMove =
    (moveLabel: string) =>
    ({ active, over }: Pick<DragEndEvent, 'active' | 'over'>) =>
      over
        ? t(moveLabel, {
            newPosition: getPosition(over.id),
            position: getPosition(active.id)
          })
        : undefined;

  const announcements: Announcements = {
    onDragCancel: ({ active }) =>
      t(labelRowDragCancelled, { position: getPosition(active.id) }),
    onDragEnd: announceMove(labelRowDropped),
    onDragOver: announceMove(labelRowMovedOver),
    onDragStart: ({ active }) =>
      t(labelRowPickedUp, { position: getPosition(active.id) })
  };

  return (
    <div className="@container w-full" ref={containerRef}>
      <ItemComposition labelAdd={translatedAddLabel} onAddItem={addEntry}>
        {[
          <DndContext
            accessibility={{
              announcements,
              screenReaderInstructions: {
                draggable: t(labelRowDragInstructions)
              }
            }}
            collisionDetection={closestCenter}
            key="sortable-entries"
            modifiers={[restrictToVerticalAxis]}
            onDragEnd={reorderEntries}
            sensors={sensors}
          >
            <SortableContext
              items={entryIds}
              strategy={verticalListSortingStrategy}
            >
              <div
                className={
                  maxVisibleRows
                    ? `relative w-full overflow-y-auto pt-2 [scrollbar-gutter:stable] [scrollbar-width:thin] ${hasHiddenRowsBelow ? '[mask-image:linear-gradient(to_bottom,#000_calc(100%-2rem),transparent)]' : ''}`
                    : 'w-full'
                }
                data-testid="sortable-entries-scroll"
                onScroll={updateHiddenRowsBelow}
                ref={scrollRef}
                style={{ maxHeight }}
              >
                <ul
                  aria-label={label}
                  className="m-0 flex w-full list-none flex-col gap-2 p-0"
                  ref={listRef}
                >
                  {entries.map(({ id, value }, index) => {
                    const rowParams = { index, value, values };

                    return (
                      <Row<T>
                        actions={actions?.(rowParams) ?? []}
                        className={getRowClassName?.(rowParams)}
                        draggable={draggable}
                        id={id}
                        key={id}
                        onDelete={removeEntry}
                        onUpdate={updateEntry}
                        renderRow={renderRow}
                        rowParams={rowParams}
                      />
                    );
                  })}
                </ul>
              </div>
            </SortableContext>
          </DndContext>
        ]}
      </ItemComposition>
    </div>
  );
};
