import * as React from 'react';

import {
  remove,
  move,
  equals,
  pipe,
  type,
  last,
  length,
  inc,
  path,
  F,
} from 'ramda';

import { makeStyles } from '@material-ui/core';

import { SelectEntry } from '../..';
import { ConnectedAutoCompleteFieldProps } from '../Connected';
import { Props as SingleAutocompletefieldProps } from '..';
import SortableList from './SortableList';

interface Props {
  onSelectedValuesChange?: (values: Array<SelectEntry>) => Array<SelectEntry>;
  initialValues?: Array<SelectEntry>;
}

const useStyles = makeStyles((theme) => ({
  helper: {
    boxShadow: theme.shadows[3],
    zIndex: theme.zIndex.tooltip,
  },
}));

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
    const [isSorting, setIsSorting] = React.useState(false);

    const classes = useStyles();

    const onDragEnd = ({ oldIndex, newIndex }) => {
      setSelectedValues(move(oldIndex, newIndex, selectedValues));
      setIsSorting(false);
    };

    const onDragStart = () => setIsSorting(true);

    const deleteValue = (index) => {
      setSelectedValues(remove(index, 1, selectedValues));
    };

    const onChange = (event, newValue) => {
      const lastValue = last(newValue);
      if (pipe(type, equals('String'))(lastValue)) {
        setSelectedValues([
          ...selectedValues,
          {
            id: inc(length(selectedValues)),
            name: lastValue,
            createOption: lastValue,
          },
        ]);
        return;
      }
      setSelectedValues(newValue);
    };

    const cancelStart = (event) =>
      pipe(
        path(['target', 'textContent']) as (object) => string,
        equals(''),
      )(event);

    const renderTags = () => {
      return (
        <SortableList
          items={selectedValues}
          axis="xy"
          onSortEnd={onDragEnd}
          onSortStart={onDragStart}
          shouldCancelStart={cancelStart}
          isSorting={isSorting}
          deleteValue={deleteValue}
          helperClass={classes.helper}
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
