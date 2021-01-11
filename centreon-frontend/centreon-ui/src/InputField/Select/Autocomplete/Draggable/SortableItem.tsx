import * as React from 'react';

import { useSortable } from '@dnd-kit/sortable';
import { CSS, Transform } from '@dnd-kit/utilities';
import { equals } from 'ramda';
import { DraggableSyntheticListeners } from '@dnd-kit/core';

import Item from './Item';

export interface ItemProps {
  name: string;
  createOption?: string;
  id: string;
  index: number;
  deleteValue: (id: number) => void;
}

interface StyledSortableProps extends ItemProps {
  transform: Transform | null;
  isDragging: boolean;
  transition?: string;
  setNodeRef: (node: HTMLElement | null) => void;
  listeners: DraggableSyntheticListeners;
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
    position: 'relative',
    display: 'inline-block',
    opacity: isDragging ? '0.7' : '1',
    transform: CSS.Translate.toString(transform),
    transition,
  };

  return (
    <Item
      {...props}
      ref={setNodeRef}
      style={style}
      listeners={listeners}
      name={name}
      createOption={createOption}
      deleteValue={deleteValue}
      index={index}
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
      listeners={listeners}
      name={name}
      createOption={createOption}
      deleteValue={deleteValue}
      index={index}
      transform={transform}
      transition={transition}
      isDragging={isDragging}
    />
  );
};

export default SortableItem;
