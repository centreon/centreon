import * as React from 'react';

import { findIndex, not, propEq } from 'ramda';
import { DraggableSyntheticListeners } from '@dnd-kit/core';
import clsx from 'clsx';

import { Chip, Typography, useTheme } from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';

import { DraggableSelectEntry, SortableListProps } from './SortableList';

interface ContentProps
  extends Pick<DraggableSelectEntry, 'name' | 'createOption' | 'id'> {
  attributes;
  id: string;
  index: number;
  isDragging: boolean;
  itemRef: React.RefObject<HTMLDivElement>;
  listeners: DraggableSyntheticListeners;
  style;
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
    const labelItemRef = React.useRef<HTMLElement | null>(null);

    const mouseUp = (event: React.MouseEvent): void => {
      if (not(event.shiftKey)) {
        return;
      }

      const itemIndex = findIndex(propEq('id', id), items);

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
          clickable
          className={clsx(classes.tag, createOption && classes.createdTag)}
          deleteIcon={<CloseIcon />}
          label={
            <Typography
              ref={labelItemRef}
              variant="body2"
              onMouseUp={mouseUp}
              {...attributes}
              {...listeners}
            >
              {name}
            </Typography>
          }
          size="small"
          style={{
            backgroundColor: isDragging ? theme.palette.grey[300] : undefined
          }}
          onDelete={deleteItem}
          onMouseEnter={mouseEnter}
          onMouseLeave={mouseLeave}
        />
      </div>
    );
  };

  return Content;
};

export default SortableListContent;
