import { Typography } from '@mui/material';
import type { AutocompleteRenderInputParams } from '@mui/material/Autocomplete';

import {
  equals,
  F,
  findIndex,
  inc,
  isEmpty,
  isNil,
  last,
  length,
  not,
  pipe,
  pluck,
  propEq,
  remove
} from 'ramda';
import {
  type ChangeEvent,
  type HTMLAttributes,
  useEffect,
  useState
} from 'react';

import TextField from '../../../Text';
import type { Props as SingleAutocompletefieldProps } from '..';
import type { ConnectedAutoCompleteFieldProps } from '../Connected';
import SortableList, { type DraggableSelectEntry } from './SortableList';

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

// biome-ignore lint/suspicious/noExplicitAny: HOC accepts varied multi-autocomplete shapes
type MultiAutocompleteLike = (props: any) => JSX.Element;

const DraggableAutocomplete = (
  MultiAutocomplete: MultiAutocompleteLike
): ((
  props: Props &
    (ConnectedAutoCompleteFieldProps<string> | SingleAutocompletefieldProps)
) => JSX.Element) => {
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
    const [selectedValues, setSelectedValues] = useState<
      Array<DraggableSelectEntry>
    >(initialValues || []);
    const [totalValues, setTotalValues] = useState<number>(
      length(initialValues || [])
    );
    const [inputText, setInputText] = useState<string | null>(null);

    const onChangeSelectedValuesOrder = (
      newSelectedValues: Array<DraggableSelectEntry>
    ): void => {
      setSelectedValues(newSelectedValues);
      onSelectedValuesChange?.(newSelectedValues);
    };

    const deleteValue = (id: string | number): void => {
      itemHover?.(null);
      setSelectedValues((values: Array<DraggableSelectEntry>) => {
        const index = findIndex(propEq(id, 'id'), values);

        const newSelectedValues = remove(index, 1, values);

        onSelectedValuesChange?.(newSelectedValues);

        return newSelectedValues;
      });
    };

    const onChange = (
      _: React.SyntheticEvent,
      newValue: Array<DraggableSelectEntry | string>
    ): void => {
      if (isEmpty(newValue)) {
        setInputText(null);
        onSelectedValuesChange?.([]);

        return;
      }
      const lastValue = last(newValue);
      if (typeof lastValue === 'string') {
        const lastDraggableItem: DraggableSelectEntry = {
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
      const lastItem = last(
        newValue as Array<DraggableSelectEntry>
      ) as DraggableSelectEntry;

      const lastDraggableItem: DraggableSelectEntry = {
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

    const changeInput = (e: ChangeEvent<HTMLInputElement>): void => {
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

    const renderOption = (
      renderProps: HTMLAttributes<HTMLLIElement>,
      option: DraggableSelectEntry
    ): JSX.Element => (
      <div key={option.id} style={{ width: '100%' }}>
        <li {...renderProps}>
          <Typography variant="body2">{option.name}</Typography>
        </li>
      </div>
    );

    const renderInput = (
      renderProps: AutocompleteRenderInputParams & {
        inputLabel?: Record<string, unknown>;
      }
    ): JSX.Element => (
      <TextField
        {...renderProps}
        dataTestId={label}
        error={error}
        helperText={error}
        label={label}
        onBlur={blurInput}
        onChange={changeInput}
        required={required}
        textFieldSlotsAndSlotProps={{
          slotProps: {
            htmlInput: {
              ...renderProps.slotProps.htmlInput,
              value: inputText || ''
            },
            input: {
              ...renderProps.slotProps.input
            },
            inputLabel: {
              ...renderProps.slotProps.inputLabel
            }
          }
        }}
      />
    );

    useEffect(() => {
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
    }, [initialValues, selectedValues]);

    return (
      <MultiAutocomplete
        disableCloseOnSelect={false}
        disableSortedOptions
        freeSolo
        handleHomeEndKeys
        isOptionEqualToValue={F}
        onChange={onChange}
        renderInput={renderInput}
        renderOption={renderOption}
        renderTags={renderTags}
        selectOnFocus
        value={selectedValues}
        {...props}
      />
    );
  };

  return InnerDraggableAutocompleteField;
};

export default DraggableAutocomplete;
