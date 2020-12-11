import * as React from 'react';

import { SortableContainer } from 'react-sortable-hoc';

import SortableItem from './SortableItem';

const SortableList = SortableContainer(
  ({ items, isSorting, deleteValue }): JSX.Element => {
    return (
      <div>
        {items.map((tag, index) => (
          <SortableItem
            key={`${tag.name}_${index.toString()}`}
            index={index}
            deleteValue={deleteValue}
            name={tag.name}
            createOption={tag.createOption}
            idx={index}
            isSorting={isSorting}
          />
        ))}
      </div>
    );
  },
);

export default SortableList;
