import { useMemo, useRef } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import {
  append,
  equals,
  inc,
  isEmpty,
  pluck,
  prop,
  reject,
  sortBy
} from 'ramda';

import { SelectEntry } from '../../..';

interface UseListState {
  addItem: (newItem: SelectEntry) => void;
  deleteItem: (id: string) => () => void;
  sortList: (items: Array<string>) => void;
  sortedList: Array<unknown>;
}

export const useList = ({ fieldName }): UseListState => {
  const { values, setFieldValue } = useFormikContext<FormikValues>();
  const maxOrder = useRef(0);

  const list = values[fieldName];

  const sortedList = useMemo(
    () =>
      sortBy(prop('order'), list).map(({ id, ...props }) => ({
        id: `${id}`,
        ...props
      })),
    [list]
  );

  const addItem = (newItem: SelectEntry): void => {
    setFieldValue(
      fieldName,
      append(
        {
          ...newItem,
          id: (newItem as SelectEntry).id as number,
          order: inc(maxOrder.current)
        },
        list
      )
    );
  };

  const deleteItem = (id: string) => (): void => {
    const newItems = reject((item) => equals(Number(id), item.id))(list);

    setFieldValue(fieldName, newItems);
  };

  const sortList = (items: Array<string>): void => {
    const newOrderedList = items.map((itemId, idx) => {
      const item = sortedList.find(({ id }) => equals(id, itemId));

      return {
        ...item,
        id: Number(item?.id),
        order: inc(idx)
      };
    });

    setFieldValue(fieldName, newOrderedList);
  };

  maxOrder.current = isEmpty(list) ? 0 : Math.max(...pluck('order', list));

  return {
    addItem,
    deleteItem,
    sortList,
    sortedList
  };
};
