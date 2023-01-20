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
  pluck
} from 'ramda';

import { Typography } from '@mui/material';

import { ConnectedAutoCompleteFieldProps } from '../Connected';
import { Props as SingleAutocompletefieldProps } from '..';
import TextField from '../../../Text';

import SortableList, { DraggableSelectEntry } from './SortableList';

export interface ItemActionProps {
  anchorElement?: HTMLElement | null;
  index: number;
  item: DraggableSelectEntry;
}

interface Props {
  error?: string;
  initialValues?: Array<DraggableSelectEntry>;
  itemClick?: (item: ItemActionProps) => void;
  itemHover?: (item: ItemActionProps | null) => void;
  label: string;
  onSelectedValuesChange?: (
    values: Array<DraggableSelectEntry>,
    valueAddedOrDeleted?: DraggableSelectEntry
  ) => Array<DraggableSelectEntry>;
  required?: boolean;
}

const DraggableAutocomplete = (
  MultiAutocomplete: (props) => JSX.Element
): ((props) => JSX.Element) => {
  const InnerDraggableAutocompleteField = ({
    onSelectedValuesChange,
    initialValues,
    itemClick,
    itemHover,
    label,
    required,
    error,
    ...props
  }: Props &
    (
      | ConnectedAutoCompleteFieldProps<string>
      | SingleAutocompletefieldProps
    )): JSX.Element => {
    const [selectedValues, setSelectedValues] = React.useState<
      Array<DraggableSelectEntry>
    >(initialValues || []);
    const [totalValues, setTotalValues] = React.useState<number>(
      length(initialValues || [])
    );
    const [inputText, setInputText] = React.useState<string | null>(null);

    const onChangeSelectedValuesOrder = (newSelectedValues): void => {
      setSelectedValues(newSelectedValues);
      onSelectedValuesChange?.(newSelectedValues);
    };

    const deleteValue = (id): void => {
      itemHover?.(null);
      setSelectedValues((values: Array<DraggableSelectEntry>) => {
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
          name: lastValue
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
        newValue
      ) as DraggableSelectEntry;

      const lastDraggableItem = {
        id: `${lastItem.name}_${totalValues}`,
        name: lastItem.name
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
          name: inputText
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

    const renderOption = (renderProps, option): JSX.Element => (
      <div key={option.id} style={{ width: '100%' }}>
        <li {...renderProps}>
          <Typography variant="body2">{option.name}</Typography>
        </li>
      </div>
    );

    const renderInput = (renderProps): JSX.Element => (
      <TextField
        {...renderProps}
        error={error}
        helperText={error}
        inputProps={{
          ...renderProps.inputProps,
          value: inputText || ''
        }}
        label={label}
        required={required}
        onBlur={blurInput}
        onChange={changeInput}
      />
    );

    React.useEffect(() => {
      if (isNil(initialValues)) {
        return;
      }

      const areValuesEqual = equals(
        pluck('name', initialValues),
        pluck('name', selectedValues as Array<DraggableSelectEntry>)
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
        isOptionEqualToValue={F}
        renderInput={renderInput}
        renderOption={renderOption}
        renderTags={renderTags}
        value={selectedValues}
        onChange={onChange}
        {...props}
      />
    );
  };

  return InnerDraggableAutocompleteField;
};

export default DraggableAutocomplete;
