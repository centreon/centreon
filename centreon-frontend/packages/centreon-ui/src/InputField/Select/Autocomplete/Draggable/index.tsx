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
} from 'ramda';

import { Typography } from '@material-ui/core';

import { SelectEntry } from '../..';
import { ConnectedAutoCompleteFieldProps } from '../Connected';
import { Props as SingleAutocompletefieldProps } from '..';

import SortableList from './SortableList';

interface Props {
  initialValues?: Array<SelectEntry>;
  onSelectedValuesChange?: (values: Array<SelectEntry>) => Array<SelectEntry>;
}

const DraggableAutocomplete = (
  MultiAutocomplete: (props) => JSX.Element,
): ((props) => JSX.Element) => {
  const InnerDraggableAutocompleteField = ({
    onSelectedValuesChange,
    initialValues,
    ...props
  }: Props &
    (ConnectedAutoCompleteFieldProps | SingleAutocompletefieldProps)) => {
    const [selectedValues, setSelectedValues] = React.useState<
      Array<SelectEntry>
    >(initialValues || []);
    const [totalValues, setTotalValues] = React.useState<number>(
      length(initialValues || []),
    );
    const [inputText, setInputText] = React.useState<string | null>(null);

    const onChangeSelectedValuesOrder = (newSelectedValues) => {
      setSelectedValues(newSelectedValues);
    };

    const deleteValue = (index) => {
      setSelectedValues((values) => remove(index, 1, values));
    };

    const onChange = (_, newValue) => {
      if (isEmpty(newValue)) {
        setSelectedValues(newValue);
        setInputText(null);

        return;
      }
      const lastValue = last(newValue);
      if (pipe(type, equals('String'))(lastValue)) {
        setSelectedValues((values) => [
          ...values,
          {
            createOption: lastValue,
            id: totalValues,
            name: lastValue,
          },
        ]);
        setTotalValues(inc(totalValues));
        setInputText(null);

        return;
      }
      const lastItem = last<SelectEntry>(newValue) as SelectEntry;
      setSelectedValues((values) => [
        ...values,
        {
          id: totalValues,
          name: lastItem.name,
        },
      ]);
      setTotalValues(inc(totalValues));
      setInputText(null);
    };

    const renderTags = () => {
      return (
        <SortableList
          changeItemsOrder={onChangeSelectedValuesOrder}
          deleteValue={deleteValue}
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
        setSelectedValues((values) => [
          ...values,
          {
            createOption: inputText,
            id: totalValues,
            name: inputText,
          },
        ]);
        setTotalValues(inc(totalValues));
      }
      setInputText(null);
    };

    const renderOption = (option): JSX.Element => (
      <Typography variant="body2">{option.name}</Typography>
    );

    React.useEffect(() => {
      onSelectedValuesChange?.(selectedValues);
    }, [selectedValues]);

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
