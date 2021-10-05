import * as React from 'react';

import {
  remove,
  equals,
  pipe,
  type,
  last,
  inc,
  F,
  length,
  isEmpty,
  isNil,
  not,
  findIndex,
  propEq,
  pluck,
} from 'ramda';

import { Typography } from '@material-ui/core';

import { ConnectedAutoCompleteFieldProps } from '../Connected';
import { Props as SingleAutocompletefieldProps } from '..';

import SortableList, { DraggableSelectEntry } from './SortableList';

export interface ItemActionProps {
  anchorElement?: HTMLElement | null;
  index: number;
  item: DraggableSelectEntry;
}

interface Props {
  initialValues?: Array<DraggableSelectEntry>;
  itemClick?: (item: ItemActionProps) => void;
  itemHover?: (item: ItemActionProps | null) => void;
  onSelectedValuesChange?: (
    values: Array<DraggableSelectEntry>,
    valueAddedOrDeleted?: DraggableSelectEntry,
  ) => Array<DraggableSelectEntry>;
}

const DraggableAutocomplete = (
  MultiAutocomplete: (props) => JSX.Element,
): ((props) => JSX.Element) => {
  const InnerDraggableAutocompleteField = ({
    onSelectedValuesChange,
    initialValues,
    itemClick,
    itemHover,
    ...props
  }: Props &
    (
      | ConnectedAutoCompleteFieldProps
      | SingleAutocompletefieldProps
    )): JSX.Element => {
    const [selectedValues, setSelectedValues] = React.useState<
      Array<DraggableSelectEntry>
    >(initialValues || []);
    const [totalValues, setTotalValues] = React.useState<number>(
      length(initialValues || []),
    );
    const [inputText, setInputText] = React.useState<string | null>(null);

    const onChangeSelectedValuesOrder = (newSelectedValues): void => {
      setSelectedValues(newSelectedValues);
      onSelectedValuesChange?.(newSelectedValues);
    };

    const deleteValue = (id): void => {
      itemHover?.(null);
      setSelectedValues((values) => {
        const index = findIndex(propEq('id', id), values);

        const newSelectedValues = remove(index, 1, values);

        onSelectedValuesChange?.(newSelectedValues);

        return newSelectedValues;
      });
    };

    const onChange = (_, newValue): void => {
      if (isEmpty(newValue)) {
        setInputText(null);
        onSelectedValuesChange?.([]);

        return;
      }
      const lastValue = last(newValue);
      if (pipe(type, equals('String'))(lastValue)) {
        const lastDraggableItem = {
          createOption: lastValue,
          id: `${lastValue}_${totalValues}`,
          name: lastValue,
        };

        setSelectedValues((values) => {
          const newSelectedValues = [...values, lastDraggableItem];
          onSelectedValuesChange?.(newSelectedValues, lastDraggableItem);

          return newSelectedValues;
        });
        setTotalValues(inc(totalValues));
        setInputText(null);

        return;
      }
      const lastItem = last<DraggableSelectEntry>(
        newValue,
      ) as DraggableSelectEntry;

      const lastDraggableItem = {
        id: `${lastItem.name}_${totalValues}`,
        name: lastItem.name,
      };

      setSelectedValues((values) => {
        const newSelectedValues = [...values, lastDraggableItem];
        onSelectedValuesChange?.(newSelectedValues, lastDraggableItem);

        return newSelectedValues;
      });
      setTotalValues(inc(totalValues));
      setInputText(null);
    };

    const renderTags = (): JSX.Element => {
      return (
        <SortableList
          changeItemsOrder={onChangeSelectedValuesOrder}
          deleteValue={deleteValue}
          itemClick={itemClick}
          itemHover={itemHover}
          items={selectedValues}
        />
      );
    };

    const changeInput = (e: React.ChangeEvent<HTMLInputElement>): void => {
      if (pipe(isNil, not)(e)) {
        setInputText(e.target.value);
      }
    };

    const blurInput = (): void => {
      if (inputText) {
        const lastItem = {
          createOption: inputText,
          id: `${inputText}_${totalValues}`,
          name: inputText,
        };

        setSelectedValues((values) => {
          const newSelectedValues = [...values, lastItem];
          onSelectedValuesChange?.(newSelectedValues, lastItem);

          return newSelectedValues;
        });
        setTotalValues(inc(totalValues));
      }
      setInputText(null);
    };

    const renderOption = (option): JSX.Element => (
      <Typography variant="body2">{option.name}</Typography>
    );

    React.useEffect(() => {
      if (isNil(initialValues)) {
        return;
      }

      const areValuesEqual = equals(
        pluck('name', initialValues),
        pluck('name', selectedValues),
      );

      if (areValuesEqual) {
        return;
      }

      setSelectedValues(initialValues);
    }, [initialValues]);

    return (
      <MultiAutocomplete
        disableSortedOptions
        freeSolo
        handleHomeEndKeys
        selectOnFocus
        disableCloseOnSelect={false}
        getOptionSelected={F}
        renderOption={renderOption}
        renderTags={renderTags}
        value={selectedValues}
        onBlur={blurInput}
        onChange={onChange}
        onInputChange={changeInput}
        {...props}
      />
    );
  };

  return InnerDraggableAutocompleteField;
};

export default DraggableAutocomplete;
