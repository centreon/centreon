import { map, find, propEq } from 'ramda';
import { rectIntersection } from '@dnd-kit/core';
import { rectSortingStrategy } from '@dnd-kit/sortable';
import { makeStyles } from 'tss-react/mui';

import { lighten } from '@mui/material';

import { SelectEntry } from '../..';
import SortableItems from '../../../../SortableItems';

import SortableListContent from './SortableListContent';

import { ItemActionProps } from '.';

export interface DraggableSelectEntry extends SelectEntry {
  id: string;
}

export interface SortableListProps {
  changeItemsOrder: (newItems: Array<DraggableSelectEntry>) => void;
  deleteValue: (id: string | number) => void;
  itemClick?: (item: ItemActionProps) => void;
  itemHover?: (item: ItemActionProps | null) => void;
  items: Array<DraggableSelectEntry>;
}

const useStyles = makeStyles()((theme) => ({
  createdTag: {
    backgroundColor: lighten(theme.palette.primary.main, 0.7)
  },
  deleteIcon: {
    height: theme.spacing(1.5),
    width: theme.spacing(1.5)
  },
  tag: {
    marginInline: theme.spacing(0.5)
  }
}));

const SortableList = ({
  items,
  deleteValue,
  changeItemsOrder,
  itemClick,
  itemHover
}: SortableListProps): JSX.Element => {
  const { classes } = useStyles();

  const dragEnd = ({ items: newItems }): void =>
    changeItemsOrder(
      map(
        (item) => find(propEq('id', item), items),
        newItems
      ) as Array<DraggableSelectEntry>
    );

  return (
    <SortableItems
      updateSortableItemsOnItemsChange
      Content={SortableListContent({
        classes,
        deleteValue,
        itemClick,
        itemHover,
        items
      })}
      collisionDetection={rectIntersection}
      itemProps={['id', 'name', 'createOption']}
      items={items}
      sortingStrategy={rectSortingStrategy}
      onDragEnd={dragEnd}
    />
  );
};

export default SortableList;
