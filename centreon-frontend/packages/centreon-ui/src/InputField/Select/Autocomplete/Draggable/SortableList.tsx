import * as React from 'react';

import { map, find, propEq } from 'ramda';
import { rectIntersection, DraggableSyntheticListeners } from '@dnd-kit/core';
import { rectSortingStrategy } from '@dnd-kit/sortable';
import clsx from 'clsx';

import { Chip, lighten, makeStyles } from '@material-ui/core';
import CloseIcon from '@material-ui/icons/Close';

import { SelectEntry } from '../..';
import SortableItems from '../../../../SortableItems';

export interface DraggableSelectEntry extends SelectEntry {
  id: string;
}

interface Props {
  changeItemsOrder: (newItems: Array<DraggableSelectEntry>) => void;
  deleteValue: (id: string | number) => void;
  items: Array<DraggableSelectEntry>;
}

interface ContentProps
  extends Pick<DraggableSelectEntry, 'name' | 'createOption' | 'id'> {
  attributes;
  id: string;
  isDragging: boolean;
  itemRef: React.RefObject<HTMLDivElement>;
  listeners: DraggableSyntheticListeners;
  style;
}

const useStyles = makeStyles((theme) => ({
  createdTag: {
    backgroundColor: lighten(theme.palette.primary.main, 0.7),
  },
  tag: {
    margin: theme.spacing(0.5),
  },
}));

const SortableList = ({
  items,
  deleteValue,
  changeItemsOrder,
}: Props): JSX.Element => {
  const classes = useStyles();

  const dragEnd = (newItems): void =>
    changeItemsOrder(
      map(
        (item) => find(propEq('id', item), items),
        newItems,
      ) as Array<DraggableSelectEntry>,
    );

  const Content = ({
    attributes,
    listeners,
    name,
    createOption,
    id,
    style,
    itemRef,
  }: ContentProps): JSX.Element => {
    return (
      <div ref={itemRef} style={style}>
        <Chip
          clickable
          className={clsx(classes.tag, createOption && classes.createdTag)}
          deleteIcon={<CloseIcon />}
          label={
            <p {...attributes} {...listeners}>
              {name}
            </p>
          }
          size="small"
          onDelete={(): void => deleteValue(id)}
        />
      </div>
    );
  };

  return (
    <SortableItems
      updateSortableItemsOnItemsChange
      Content={Content}
      collisionDetection={rectIntersection}
      itemProps={['id', 'name', 'createOption']}
      items={items}
      sortingStrategy={rectSortingStrategy}
      onDragEnd={dragEnd}
    />
  );
};

export default SortableList;
