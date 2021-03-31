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
} from 'ramda';

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

    const onChangeSelectedValuesOrder = (newSelectedValues) => {
      setSelectedValues(newSelectedValues);
    };

    const deleteValue = (index) => {
      setSelectedValues((values) => remove(index, 1, values));
    };

    const onChange = (_, newValue) => {
      if (isEmpty(newValue)) {
        setSelectedValues(newValue);
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

    React.useEffect(() => {
      onSelectedValuesChange?.(selectedValues);
    }, [selectedValues]);

    return (
      <MultiAutocomplete
        clearOnBlur
        freeSolo
        handleHomeEndKeys
        selectOnFocus
        disableCloseOnSelect={false}
        displayCheckboxOption={false}
        getOptionSelected={F}
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
