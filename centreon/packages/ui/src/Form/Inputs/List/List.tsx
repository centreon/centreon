import { ComponentType } from 'react';

import { closestCenter } from '@dnd-kit/core';
import { verticalListSortingStrategy } from '@dnd-kit/sortable';
import { useTranslation } from 'react-i18next';

import { SortableItems, Subtitle } from '../../..';
import { InputPropsWithoutGroup } from '../models';

import Content, { ContentProps } from './Content';
import { useListStyles } from './List.styles';
import { useList } from './useList';

const List = ({
  list,
  fieldName
}: InputPropsWithoutGroup): JSX.Element | null => {
  const { t } = useTranslation();
  const { classes } = useListStyles();

  const { addItem, sortList, sortedList, deleteItem } = useList({ fieldName });

  const { AddItem, addItemLabel, sortLabel, SortContent, itemProps } = list as {
    AddItem: ComponentType<{ addItem }>;
    SortContent: ComponentType;
    addItemLabel?: string;
    itemProps: Array<string>;
    sortLabel?: string;
  };

  return (
    <div className={classes.list}>
      {addItemLabel && <Subtitle>{t(addItemLabel)}</Subtitle>}
      <AddItem addItem={addItem} />
      {sortLabel && <Subtitle>{t(sortLabel)}</Subtitle>}
      <div className={classes.items}>
        <SortableItems
          updateSortableItemsOnItemsChange
          // eslint-disable-next-line react/no-unstable-nested-components
          Content={(props: Omit<ContentProps, 'children' | 'deleteItem'>) => (
            <Content {...props} deleteItem={deleteItem}>
              <SortContent {...props} />
            </Content>
          )}
          collisionDetection={closestCenter}
          itemProps={itemProps}
          items={sortedList}
          sortingStrategy={verticalListSortingStrategy}
          onDragEnd={({ items }): void => {
            sortList(items);
          }}
        />
      </div>
    </div>
  );
};

export default List;
