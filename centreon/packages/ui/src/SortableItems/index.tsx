import * as React from 'react';

import {
  DndContext,
  DragOverlay,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  CollisionDetection,
  DraggableSyntheticListeners,
  DragOverEvent,
  DragStartEvent,
  DragEndEvent,
  Over
} from '@dnd-kit/core';
import {
  SortableContext,
  sortableKeyboardCoordinates,
  SortingStrategy
} from '@dnd-kit/sortable';
import {
  equals,
  find,
  indexOf,
  isNil,
  move,
  not,
  path,
  pipe,
  propEq,
  pick,
  pluck
} from 'ramda';

import { useTheme } from '@mui/material';

import SortableItem from './SortableItem';
import Item from './Item';

interface ContentProps {
  attributes;
  index;
  isDragging: boolean;
  itemRef: React.RefObject<HTMLDivElement>;
  listeners: DraggableSyntheticListeners;
  style;
}

export interface RootComponentProps {
  children: JSX.Element | null;
  isInDragOverlay?: boolean;
}

export interface DragEnd {
  event: DragEndEvent;
  items: Array<string>;
}

const DefaultRootComponent = ({ children }: RootComponentProps): JSX.Element =>
  children as JSX.Element;

interface Props<T> {
  Content: ({
    isDragging,
    listeners,
    attributes,
    style,
    itemRef,
    index,
    ...other
  }: ContentProps & T) => JSX.Element | null;
  RootComponent?: ({
    children,
    isInDragOverlay
  }: RootComponentProps) => JSX.Element;
  additionalProps?: Array<unknown>;
  collisionDetection: CollisionDetection;
  getDisableItemCondition?: (item: T) => boolean;
  getDisableOverItemSortableCondition?: (overItem: Over) => boolean;
  itemProps: Array<string>;
  items: Array<T>;
  memoProps?: Array<unknown>;
  onDragEnd?: (props: DragEnd) => void;
  onDragOver?: (event: DragOverEvent) => void;
  onDragStart?: (event: DragStartEvent) => void;
  sortingStrategy: SortingStrategy;
  updateSortableItemsOnItemsChange?: boolean;
}

const propertyToFilterItemsOn = 'id';

const SortableItems = <T extends { [propertyToFilterItemsOn]: string }>({
  items,
  onDragStart,
  onDragEnd,
  onDragOver,
  collisionDetection,
  sortingStrategy,
  itemProps,
  memoProps = [],
  additionalProps,
  RootComponent = DefaultRootComponent,
  Content,
  getDisableItemCondition = (): boolean => false,
  getDisableOverItemSortableCondition = (): boolean => false,
  updateSortableItemsOnItemsChange = false
}: Props<T>): JSX.Element => {
  const getItemsIds = (): Array<string> =>
    pluck(propertyToFilterItemsOn, items);

  const [activeId, setActiveId] = React.useState<string | null>(null);
  const [sortableItemsIds, setSortableItemsIds] = React.useState(getItemsIds());

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
      keyboardCodes: {
        cancel: ['Escape'],
        end: ['Space', 'Enter'],
        start: [
          'ArrowUp',
          'ArrowDown',
          'ArrowLeft',
          'ArrowRight',
          'Enter',
          'Space'
        ]
      },
      scrollBehavior: 'smooth'
    })
  );
  const theme = useTheme();

  const isOverItemDisabled = (overItem: Over | null): boolean => {
    return (overItem && getDisableOverItemSortableCondition(overItem)) || false;
  };

  const dragStart = (event: DragStartEvent): void => {
    onDragStart?.(event);
    setActiveId(path(['active', propertyToFilterItemsOn], event) as string);
  };

  const dragCancel = (): void => setActiveId(null);

  const dragEnd = (event: DragEndEvent): void => {
    const overId = path(['over', propertyToFilterItemsOn], event);
    if (
      pipe(isNil, not)(overId) &&
      pipe(equals(activeId), not)(overId as string | null) &&
      not(isOverItemDisabled(event.over))
    ) {
      const oldIndex = indexOf(activeId, sortableItemsIds);
      const newIndex = indexOf(overId, sortableItemsIds);

      const newItemsOrder = move<string>(oldIndex, newIndex, sortableItemsIds);

      setSortableItemsIds(newItemsOrder);
      onDragEnd?.({ event, items: newItemsOrder });
    }
    setActiveId(null);
  };

  const dragOver = (event: DragOverEvent): void => {
    onDragOver?.(event);
  };

  const getItemById = (id): T | undefined =>
    find(propEq(propertyToFilterItemsOn, id), items);

  const activeItem = getItemById(activeId) as Record<string, unknown>;

  React.useEffect(() => {
    if (not(updateSortableItemsOnItemsChange)) {
      return;
    }
    setSortableItemsIds(getItemsIds());
  }, [items]);

  return (
    <DndContext
      collisionDetection={collisionDetection}
      sensors={sensors}
      onDragCancel={dragCancel}
      onDragEnd={dragEnd}
      onDragOver={dragOver}
      onDragStart={dragStart}
    >
      <SortableContext items={sortableItemsIds} strategy={sortingStrategy}>
        <RootComponent>
          <>
            {sortableItemsIds.map((sortableItemId, index) => {
              const item = getItemById(sortableItemId) as
                | Record<string, unknown>
                | undefined;

              if (isNil(item)) {
                return null;
              }

              return (
                not(getDisableItemCondition(item as T)) && (
                  <SortableItem
                    Content={Content}
                    index={index}
                    itemId={sortableItemId}
                    itemProps={itemProps}
                    key={sortableItemId}
                    memoProps={memoProps}
                    {...pick(itemProps, item)}
                    additionalProps={additionalProps}
                  />
                )
              );
            })}
          </>
        </RootComponent>
      </SortableContext>
      <DragOverlay style={{ zIndex: theme.zIndex.tooltip }}>
        <RootComponent isInDragOverlay>
          {activeId ? (
            <Item
              isDragging
              isInDragOverlay
              Content={Content}
              title={activeId}
              {...pick(itemProps, activeItem)}
              {...additionalProps}
            />
          ) : null}
        </RootComponent>
      </DragOverlay>
    </DndContext>
  );
};

export default SortableItems;
