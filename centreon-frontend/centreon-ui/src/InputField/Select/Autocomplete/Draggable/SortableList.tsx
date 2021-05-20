import * as React from 'react';

import {
  move,
  map,
  join,
  props,
  pipe,
  equals,
  not,
  path,
  isNil,
  indexOf,
} from 'ramda';
import {
  DndContext,
  rectIntersection,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  DragOverlay,
} from '@dnd-kit/core';
import {
  SortableContext,
  sortableKeyboardCoordinates,
} from '@dnd-kit/sortable';

import { useTheme } from '@material-ui/core';

import { SelectEntry } from '../..';

import SortableItem from './SortableItem';
import Item from './Item';

interface Props {
  changeItemsOrder: (newItems: Array<SelectEntry>) => void;
  deleteValue: (id: number) => void;
  items: Array<SelectEntry>;
}

const SortableList = ({
  items,
  deleteValue,
  changeItemsOrder,
}: Props): JSX.Element => {
  const [activeId, setActiveId] = React.useState<string | null>(null);
  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    }),
  );
  const theme = useTheme();

  const sortableItems = React.useMemo(
    () => map(pipe(props(['name', 'id']), join('_')), items),
    [items],
  );

  const dragOver = (event): void => {
    const overId = path(['over', 'id'], event);

    if (
      pipe(isNil, not)(overId) &&
      pipe(equals(activeId), not)(overId as string | null)
    ) {
      const oldIndex = indexOf(activeId, sortableItems);
      const newIndex = indexOf(overId, sortableItems);
      changeItemsOrder(move(oldIndex, newIndex, items));
    }
  };

  const dragStart = (event) => {
    setActiveId(path(['active', 'id'], event) as string);
  };

  const dragCancel = () => setActiveId(null);

  const dragEnd = () => setActiveId(null);

  const getActiveElement = () => {
    if (isNil(activeId)) {
      return null;
    }
    const index = indexOf(activeId, sortableItems);
    return {
      ...items[index],
      index,
    };
  };

  const activeElement = getActiveElement();

  return (
    <div>
      <DndContext
        collisionDetection={rectIntersection}
        sensors={sensors}
        onDragCancel={dragCancel}
        onDragEnd={dragEnd}
        onDragOver={dragOver}
        onDragStart={dragStart}
      >
        <SortableContext items={sortableItems} strategy={undefined}>
          {items.map(({ name, id, createOption }, index) => (
            <SortableItem
              createOption={createOption}
              deleteValue={deleteValue}
              id={`${name}_${id}`}
              index={index}
              key={`${name}_${id}`}
              name={name}
            />
          ))}
        </SortableContext>
        <DragOverlay>
          {activeId && (
            <Item
              chipStyle={{
                boxShadow: theme.shadows[3],
              }}
              createOption={activeElement?.createOption}
              deleteValue={deleteValue}
              index={activeElement?.index as number}
              name={activeElement?.name as string}
              style={{
                zIndex: theme.zIndex.tooltip,
              }}
            />
          )}
        </DragOverlay>
      </DndContext>
    </div>
  );
};

export default SortableList;
