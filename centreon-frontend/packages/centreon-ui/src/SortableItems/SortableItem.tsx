import * as React from 'react';

import { props } from 'ramda';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

import useMemoComponent from '../utils/useMemoComponent';

import Item from './Item';

interface Props extends Record<string, unknown> {
  additionalProps: Array<unknown> | undefined;
  itemId: string;
  memoProps: Array<string>;
}

const SortableItem = ({
  itemId,
  memoProps,
  additionalProps = [],
  ...rest
}: Props): JSX.Element => {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    isDragging,
    transition,
  } = useSortable({ id: itemId });

  const style = {
    opacity: isDragging ? '0.7' : '1',
    transform: CSS.Translate.toString(transform),
    transition,
  };

  return useMemoComponent({
    Component: (
      <Item
        {...rest}
        {...additionalProps}
        attributes={attributes}
        isDragging={isDragging}
        listeners={listeners}
        ref={setNodeRef}
        style={style}
      />
    ),
    memoProps: [
      isDragging,
      transform,
      ...props(memoProps, rest),
      ...additionalProps,
    ],
  });
};

export default SortableItem;
