import * as React from 'react';

import { useSortable } from '@dnd-kit/sortable';
import { CSS, Transform } from '@dnd-kit/utilities';
import { equals } from 'ramda';
import { DraggableSyntheticListeners } from '@dnd-kit/core';

import Item from './Item';

export interface ItemProps {
  createOption?: string;
  deleteValue: (id: number) => void;
  id: string;
  index: number;
  name: string;
}

interface StyledSortableProps extends ItemProps {
  isDragging: boolean;
  listeners: DraggableSyntheticListeners;
  setNodeRef: (node: HTMLElement | null) => void;
  transform: Transform | null;
  transition: string | undefined;
}

const StyledSortableItem = ({
  name,
  createOption,
  index,
  deleteValue,
  transform,
  isDragging,
  transition,
  setNodeRef,
  listeners,
  ...props
}: Omit<StyledSortableProps, 'id'>): JSX.Element => {
  const style: React.CSSProperties = {
    display: 'inline-block',
    opacity: isDragging ? '0.7' : '1',
    position: 'relative',
    transform: CSS.Translate.toString(transform),
    transition: transition || undefined,
  };

  return (
    <Item
      {...props}
      createOption={createOption}
      deleteValue={deleteValue}
      index={index}
      listeners={listeners}
      name={name}
      ref={setNodeRef}
      style={style}
    />
  );
};

const MemoizedStyledDraggableItem = React.memo(
  StyledSortableItem,
  (prevProps, nextProps) =>
    equals(prevProps.name, nextProps.name) &&
    equals(prevProps.createOption, nextProps.createOption) &&
    equals(prevProps.index, nextProps.index) &&
    equals(prevProps.transform, nextProps.transform) &&
    equals(prevProps.isDragging, nextProps.isDragging),
);

const SortableItem = ({
  name,
  createOption,
  id,
  index,
  deleteValue,
}: ItemProps): JSX.Element => {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id });

  return (
    <MemoizedStyledDraggableItem
      setNodeRef={setNodeRef}
      {...attributes}
      createOption={createOption}
      deleteValue={deleteValue}
      index={index}
      isDragging={isDragging}
      listeners={listeners}
      name={name}
      transform={transform}
      transition={transition}
    />
  );
};

export default SortableItem;
