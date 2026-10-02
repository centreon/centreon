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
  onChange: (values: Array<T>) => void;
  /** Renders the inputs of one row. `values` gives access to sibling rows. */
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

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates
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
    <div ref={containerRef}>
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
              <ul
                aria-label={label}
                className="m-0 flex w-full list-none flex-col gap-2 p-0"
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
            </SortableContext>
          </DndContext>
        ]}
      </ItemComposition>
    </div>
  );
};
