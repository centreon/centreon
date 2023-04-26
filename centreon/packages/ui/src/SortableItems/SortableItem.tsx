import { props } from 'ramda';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

import useMemoComponent from '../utils/useMemoComponent';

import Item from './Item';

interface Props extends Record<string, unknown> {
  additionalProps: Array<unknown> | undefined;
  index: number;
  itemId: string;
  itemProps: Array<string>;
  memoProps: Array<unknown>;
}

const SortableItem = ({
  itemId,
  memoProps,
  additionalProps = [],
  itemProps,
  index,
  ...rest
}: Props): JSX.Element => {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    isDragging,
    transition
  } = useSortable({ id: itemId });

  const style = {
    opacity: isDragging ? '0.7' : '1',
    transform: CSS.Translate.toString(transform),
    transition
  };

  return useMemoComponent({
    Component: (
      <Item
        {...rest}
        {...additionalProps}
        attributes={attributes}
        index={index}
        isDragging={isDragging}
        listeners={listeners}
        ref={setNodeRef}
        style={style}
      />
    ),
    memoProps: [
      index,
      isDragging,
      transform,
      props(itemProps, rest),
      ...memoProps,
      ...additionalProps
    ]
  });
};

export default SortableItem;
