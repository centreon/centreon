import * as React from 'react';

import { remove, equals, pipe, type, last, length, inc, F } from 'ramda';

import { SelectEntry } from '../..';
import { ConnectedAutoCompleteFieldProps } from '../Connected';
import { Props as SingleAutocompletefieldProps } from '..';

import SortableList from './SortableList';

interface Props {
  onSelectedValuesChange?: (values: Array<SelectEntry>) => Array<SelectEntry>;
  initialValues?: Array<SelectEntry>;
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
      setSelectedValues(remove(index, 1, selectedValues));
    };

    const onChange = (_, newValue) => {
      const lastValue = last(newValue);
      if (pipe(type, equals('String'))(lastValue)) {
        setSelectedValues([
          ...selectedValues,
          {
            id: totalValues,
            name: lastValue,
            createOption: lastValue,
          },
        ]);
        setTotalValues(inc(totalValues));
        return;
      }
      const lastItem = last<SelectEntry>(newValue) as SelectEntry;
      setSelectedValues([
        ...selectedValues,
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
          items={selectedValues}
          deleteValue={deleteValue}
          changeItemsOrder={onChangeSelectedValuesOrder}
        />
      );
    };

    React.useEffect(() => {
      onSelectedValuesChange?.(selectedValues);
    }, [selectedValues]);

    return (
      <MultiAutocomplete
        value={selectedValues}
        selectOnFocus
        clearOnBlur
        freeSolo
        handleHomeEndKeys
        renderTags={renderTags}
        onChange={onChange}
        disableCloseOnSelect={false}
        displayCheckboxOption={false}
        getOptionSelected={F}
        {...props}
      />
    );
  };

  return InnerDraggableAutocompleteField;
};

export default DraggableAutocomplete;
