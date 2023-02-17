import { useEffect, useState } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';
import { verticalListSortingStrategy } from '@dnd-kit/sortable';
import { DraggableSyntheticListeners, rectIntersection } from '@dnd-kit/core';
import {
  dec,
  equals,
  inc,
  isNil,
  pick,
  split,
  path,
  type,
  not,
  filter,
  pipe,
  map,
  or,
  is,
  clone
} from 'ramda';

import { FormHelperText, IconButton, Typography } from '@mui/material';
import UnfoldMoreIcon from '@mui/icons-material/UnfoldMore';

import { userAtom } from '@centreon/ui-context';

import { DragEnd } from '../../../SortableItems';
import { SortableItems, useMemoComponent } from '../../..';
import {
  InputPropsWithoutGroup,
  InputPropsWithoutGroupAndType
} from '../models';

import Row from './Row';

interface StylesProps {
  columns?: number;
  isDragging?: boolean;
}

const useStyles = makeStyles<StylesProps>()((theme, { columns }) => ({
  container: {
    display: 'flex',
    flexDirection: 'column',
    rowGap: theme.spacing(1)
  },
  icon: {
    marginTop: theme.spacing(0.5)
  },
  inputsRow: {
    columnGap: theme.spacing(2),
    display: 'grid',
    gridTemplateColumns: `repeat(${columns}, 1fr) min-content`
  },
  table: {
    display: 'flex',
    flexDirection: 'column',
    rowGap: theme.spacing(2)
  }
}));

const useContentStyles = makeStyles<StylesProps>()((theme, { isDragging }) => ({
  content: {
    '&:hover': {
      boxShadow: theme.shadows[3]
    },
    padding: theme.spacing(1)
  },
  handler: {
    cursor: isDragging ? 'grabbing' : 'grab'
  }
}));

interface TableRowValue {
  [key: string]: unknown;
  priority?: number;
}

interface FieldsTableContextProps extends InputPropsWithoutGroupAndType {
  defaultRowValue?: TableRowValue | string;
  fieldsTableRows: number;
  onDeleteRow: (rowIndex: number) => void;
}

interface Entity extends FieldsTableContextProps {
  id: string;
}

interface ContentProps extends Entity {
  attributes;
  index: number;
  isDragging: boolean;
  itemRef: React.RefObject<HTMLDivElement>;
  listeners: DraggableSyntheticListeners;
  style;
}

const SortableRow = ({
  id,
  index,
  listeners,
  style,
  onDeleteRow,
  isDragging,
  itemRef,
  fieldsTable,
  fieldsTableRows,
  defaultRowValue,
  label,
  fieldName
}: ContentProps): JSX.Element | null => {
  const { classes } = useContentStyles({ isDragging });

  const { values } = useFormikContext<FormikValues>();

  if (isNil(fieldName)) {
    return null;
  }
  const getRequired = (): boolean =>
    fieldsTable?.getRequired?.({ index, values }) || false;

  const isLastElement = equals(index, dec(fieldsTableRows));

  const deleteRow = (): void => {
    onDeleteRow(index);
  };

  return (
    <div key={`${label}_${id}`} ref={itemRef} style={style}>
      <Row
        additionalActions={
          <IconButton size="small" {...listeners} className={classes.handler}>
            <UnfoldMoreIcon fontSize="small" />
          </IconButton>
        }
        columns={fieldsTable?.columns}
        defaultRowValue={defaultRowValue}
        deleteLabel={fieldsTable?.deleteLabel}
        getRequired={getRequired}
        hasSingleValue={fieldsTable?.hasSingleValue}
        index={Number(id)}
        isLastElement={isLastElement}
        label={label}
        tableFieldName={fieldName}
        onDeleteRow={deleteRow}
      />
    </div>
  );
};

const FieldsTable = ({
  fieldsTable,
  fieldName,
  label
}: InputPropsWithoutGroup): JSX.Element => {
  const { classes } = useStyles({
    columns: fieldsTable?.columns.length
  });

  const { t } = useTranslation();

  const [isSortable, setIsSortable] = useState(false);

  const getSortableDefined = not(isNil(fieldsTable?.getSortable));

  const { themeMode } = useAtomValue(userAtom);

  const { values, errors, setFieldValue } = useFormikContext<FormikValues>();

  const fieldNamePath = split('.', fieldName);

  const tableValues = path(fieldNamePath, values) as Array<TableRowValue>;

  const fieldsTableError = path(fieldNamePath, errors) as string | undefined;

  const defaultRowValue = is(Object, fieldsTable?.defaultRowValue)
    ? {
        ...(fieldsTable?.defaultRowValue as TableRowValue),
        priority: tableValues.length
      }
    : fieldsTable?.defaultRowValue;

  const fieldsToMemoize = pick(
    fieldsTable?.additionalFieldsToMemoize || [],
    values
  );

  const createNewRow = isNil(fieldsTableError);

  const fieldsTableRows = createNewRow
    ? inc(tableValues.length)
    : tableValues.length;

  /**
   * This function is used to remove a row through the requested index from an array and update the priority based on the remaining rows.
   *
   * @param indexToRemove
   * @param arrayToUpdate
   * @returns {Array<TableRowValue>} the updated array
   */
  const deleteRowAndUpdatePriority = (
    indexToRemove: number,
    arrayToUpdate: Array<TableRowValue>
  ): Array<TableRowValue> => {
    const filterIndexToRemoveAndUpdatePriority = pipe(
      filter((item: TableRowValue) => item.priority !== indexToRemove),
      map((item: TableRowValue) =>
        item.priority && item.priority > indexToRemove
          ? {
              ...item,
              priority: dec(item.priority)
            }
          : item
      )
    );

    return filterIndexToRemoveAndUpdatePriority(arrayToUpdate);
  };

  const onDeleteRow = (index: number): void => {
    setFieldValue(fieldName, deleteRowAndUpdatePriority(index, tableValues));
  };

  const isNotSortableOrLastEmptyItem = (index: number): boolean =>
    or(not(getSortableDefined), isNil(tableValues[index]?.priority));

  /**
   * @var keysToIterate contains the keys of the FieldsTable array to display, They are based on a priority field.
   * If the priority field is not present in the formik table values, the keys are equals to the indexes of the array.
   * @return {Array<number>} keysToIterate
   */
  const keysToIterate = [...Array(fieldsTableRows).keys()].reduce(
    (acc, _, index) => {
      const itemPosition = Number(
        isNotSortableOrLastEmptyItem(index)
          ? index
          : tableValues[index].priority
      );
      acc[itemPosition] = index;

      return acc;
    },
    [] as Array<number>
  );

  /**
   * @var sortableItems are the entities to display in the FieldsTable. Its values are bases on {@link keysToIterate}.
   * @return {Array<Entity>} sortableItems
   */
  const sortableItems = keysToIterate.map((id) => ({
    defaultRowValue,
    fieldName,
    fieldsTable,
    fieldsTableRows,
    id: String(id),
    label,
    onDeleteRow
  }));

  useEffect(() => {
    if (getSortableDefined) {
      setIsSortable(fieldsTable?.getSortable?.(values) || false);
    }
  }, [fieldName, fieldsTable, fieldsTableRows, label, values]);

  const updatePriorities = (items): Array<unknown> =>
    items.reduce((acc, curr, index) => {
      const row = acc[curr];

      if (isNil(row)) {
        return acc;
      }

      row.priority = index;

      return acc;
    }, clone(tableValues));

  const dragEnd = ({ items }: DragEnd): void => {
    const updatedPriorities = updatePriorities(items);
    setFieldValue(fieldName, updatedPriorities);
  };

  const disableOverItemSortableCondition = ({ id }): boolean =>
    Number(id) === tableValues.length || not(isNil(fieldsTableError));

  return useMemoComponent({
    Component: (
      <div className={classes.container}>
        <Typography>{t(label)}</Typography>
        <div className={classes.table}>
          {isSortable ? (
            <SortableItems<Entity>
              updateSortableItemsOnItemsChange
              Content={SortableRow}
              collisionDetection={rectIntersection}
              getDisableOverItemSortableCondition={
                disableOverItemSortableCondition
              }
              itemProps={[
                'defaultRowValue',
                'fieldName',
                'fieldsTable',
                'fieldsTableRows',
                'id',
                'label',
                'onDeleteRow'
              ]}
              items={sortableItems}
              sortingStrategy={verticalListSortingStrategy}
              onDragEnd={dragEnd}
            />
          ) : (
            keysToIterate.map((idx): JSX.Element => {
              const getRequired = (): boolean =>
                fieldsTable?.getRequired?.({ index: idx, values }) || false;

              const isLastElement = equals(Number(idx), dec(fieldsTableRows));

              return (
                <div key={`${label}_${idx}`}>
                  <Row
                    columns={fieldsTable?.columns}
                    defaultRowValue={defaultRowValue}
                    deleteLabel={fieldsTable?.deleteLabel}
                    getRequired={getRequired}
                    hasSingleValue={fieldsTable?.hasSingleValue}
                    index={idx}
                    isLastElement={isLastElement}
                    label={label}
                    tableFieldName={fieldName}
                    onDeleteRow={getSortableDefined ? onDeleteRow : undefined}
                  />
                </div>
              );
            })
          )}
        </div>
        {equals(type(fieldsTableError), 'String') && (
          <FormHelperText error>{fieldsTableError}</FormHelperText>
        )}
      </div>
    ),
    memoProps: [
      tableValues,
      fieldsTableError,
      themeMode,
      fieldsToMemoize,
      fieldsTableRows,
      fieldName,
      fieldsTable,
      label,
      isSortable,
      keysToIterate
    ]
  });
};

export default FieldsTable;
