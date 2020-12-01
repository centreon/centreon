import * as React from 'react';

import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';
import {
  remove,
  move,
  not,
  prop,
  equals,
  pipe,
  type,
  last,
  length,
  inc,
} from 'ramda';
import clsx from 'clsx';

import { Chip, makeStyles, lighten, useTheme } from '@material-ui/core';
import { createFilterOptions } from '@material-ui/lab/Autocomplete';
import CloseIcon from '@material-ui/icons/Close';

import TextField from '../../../Text';
import { SelectEntry } from '../..';
import { ConnectedAutoCompleteFieldProps } from '../Connected';
import { MultiAutocompleteFieldProps } from '../../../..';

const filter = createFilterOptions();

const reorder = (list, startIndex, endIndex) => {
  const result = Array.from(list);
  return move(startIndex, endIndex, result);
};

const useStyles = makeStyles((theme) => ({
  tag: {
    margin: theme.spacing(0.5),
  },
  createdTag: {
    backgroundColor: lighten(theme.palette.primary.main, 0.7),
  },
  list: {
    display: 'flex',
    overflow: 'auto',
    maxWidth: '80%',
    borderRight: `1px solid ${theme.palette.grey[400]}`,
  },
}));

interface Props {
  onSelectedValuesChange?: (values: Array<SelectEntry>) => Array<SelectEntry>;
  label?: string;
  initialValues?: Array<SelectEntry>;
}

const DraggableAutocomplete = (
  MultiAutocomplete: (props) => JSX.Element,
): ((props) => JSX.Element) => {
  const InnerDraggableAutocompleteField = ({
    onSelectedValuesChange,
    label,
    initialValues,
    ...props
  }: Props &
    (ConnectedAutoCompleteFieldProps | MultiAutocompleteFieldProps)) => {
    const [selectedValues, setSelectedValues] = React.useState<
      Array<SelectEntry>
    >(initialValues || []);
    const classes = useStyles();
    const theme = useTheme();

    const onDragEnd = (result) => {
      // dropped outside the list
      if (not(prop('destination', result))) {
        return;
      }

      const items = reorder(
        selectedValues,
        result.source.index,
        result.destination.index,
      ) as Array<SelectEntry>;

      setSelectedValues(items);
    };

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

    const filterOptions = (options, params) => {
      const filtered = filter(options, params);

      if (
        pipe(prop('inputValue') as (value) => string, equals(''), not)(params)
      ) {
        filtered.push({
          id: inc(length(selectedValues)),
          name: params.inputValue,
          createOption: params.inputValue,
        });
      }

      return filtered;
    };

    const renderTags = (tags) => {
      return (
        <DragDropContext onDragEnd={onDragEnd}>
          <Droppable droppableId="droppable" direction="horizontal">
            {(provided) => (
              <div
                ref={provided.innerRef}
                {...provided.droppableProps}
                className={classes.list}
              >
                {tags.map((tag, index) => (
                  <Draggable
                    key={`${tag.name}_${index.toString()}`}
                    draggableId={`${tag.name}_${index}`}
                    index={index}
                  >
                    {(providedDraggable, snapshot) => (
                      <div
                        ref={providedDraggable.innerRef}
                        {...providedDraggable.draggableProps}
                        {...providedDraggable.dragHandleProps}
                      >
                        <Chip
                          size="small"
                          label={tag.name}
                          className={clsx(
                            classes.tag,
                            tag.createOption && classes.createdTag,
                          )}
                          style={
                            snapshot.isDragging
                              ? { boxShadow: theme.shadows[3] }
                              : {}
                          }
                          clickable
                          onDelete={() => deleteValue(index)}
                          deleteIcon={<CloseIcon />}
                        />
                      </div>
                    )}
                  </Draggable>
                ))}
                {provided.placeholder}
              </div>
            )}
          </Droppable>
        </DragDropContext>
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
        renderInput={(params) => <TextField {...params} label={label} />}
        onChange={onChange}
        filterOptions={filterOptions}
        {...props}
      />
    );
  };

  return InnerDraggableAutocompleteField;
};

export default DraggableAutocomplete;
