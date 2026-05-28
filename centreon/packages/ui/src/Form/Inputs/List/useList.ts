import { type FormikValues, useFormikContext } from 'formik';
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
import { useMemo, useRef } from 'react';

import type { SelectEntry } from '../../..';

interface UseListState {
  addItem: (newItem: SelectEntry) => void;
  deleteItem: (id: string) => () => void;
  sortList: (items: Array<string>) => void;
  sortedList: Array<unknown>;
}

export const useList = ({ fieldName }: { fieldName: string }): UseListState => {
  const { values, setFieldValue } = useFormikContext<FormikValues>();
  const maxOrder = useRef(0);

  const list = values[fieldName];

  const sortedList = useMemo(
    () =>
      sortBy(prop('order') as (a: Record<string, unknown>) => number, list).map(
        ({ id, ...props }: Record<string, unknown>) => ({
          id: `${id}`,
          ...props
        })
      ),
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
    const newItems = reject((item: Record<string, unknown>) =>
      equals(Number(id), item.id)
    )(list);

    setFieldValue(fieldName, newItems);
  };

  const sortList = (items: Array<string>): void => {
    const newOrderedList = items.map((itemId, idx) => {
      const item = sortedList.find(({ id }: Record<string, unknown>) =>
        equals(id, itemId)
      );

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
    sortedList,
    sortList
  };
};
