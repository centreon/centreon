import { Chip, Typography, useTheme } from '@mui/material';

import type { DraggableSyntheticListeners } from '@dnd-kit/core';
import clsx from 'clsx';
import { findIndex, not, propEq } from 'ramda';
import type React from 'react';
import { type CSSProperties, type RefObject, useRef } from 'react';

import type { DraggableSelectEntry, SortableListProps } from './SortableList';

interface ContentProps
  extends Pick<DraggableSelectEntry, 'name' | 'createOption' | 'id'> {
  // biome-ignore lint/suspicious/noExplicitAny: dnd-kit forwards arbitrary HTML attributes
  attributes: Record<string, any>;
  id: string;
  index: number;
  isDragging: boolean;
  itemRef: RefObject<HTMLDivElement>;
  listeners: DraggableSyntheticListeners;
  style: CSSProperties;
}

interface Props extends Omit<SortableListProps, 'changeItemsOrder'> {
  classes: Record<string, string>;
}

const SortableListContent = ({
  items,
  classes,
  itemHover,
  itemClick,
  deleteValue
}: Props): ((props: ContentProps) => JSX.Element) => {
  const Content = ({
    attributes,
    listeners,
    name,
    createOption,
    id,
    style,
    itemRef,
    index,
    isDragging
  }: ContentProps): JSX.Element => {
    const theme = useTheme();
    const labelItemRef = useRef<HTMLElement | null>(null);

    const mouseUp = (event: React.MouseEvent<HTMLElement>): void => {
      if (not(event.shiftKey)) {
        return;
      }

      const itemIndex = findIndex(propEq(id, 'id'), items);

      itemHover?.(null);
      itemClick?.({ index: itemIndex, item: { createOption, id, name } });
    };

    const mouseLeave = (): void => itemHover?.(null);

    const mouseEnter = (): void =>
      itemHover?.({
        anchorElement: labelItemRef.current,
        index,
        item: { createOption, id, name }
      });

    const deleteItem = (): void => deleteValue(id);

    return (
      <div ref={itemRef} style={style}>
        <Chip
          classes={{
            deleteIcon: classes.deleteIcon
          }}
          className={clsx(classes.tag, createOption && classes.createdTag)}
          clickable
          label={
            <Typography
              onMouseUp={mouseUp}
              ref={labelItemRef}
              variant="body2"
              {...attributes}
              {...listeners}
            >
              {name}
            </Typography>
          }
          onDelete={deleteItem}
          onMouseEnter={mouseEnter}
          onMouseLeave={mouseLeave}
          size="medium"
          style={{
            backgroundColor: isDragging ? theme.palette.grey[300] : undefined
          }}
        />
      </div>
    );
  };

  return Content;
};

export default SortableListContent;
