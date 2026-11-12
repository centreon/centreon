import EditIcon from '@mui/icons-material/Edit';
import { Typography } from '@mui/material';

import {
  type InputPropsWithoutGroup,
  type SearchParameter,
  type SelectEntry,
  SingleConnectedAutocompleteField
} from '@centreon/ui';
import {
  type RenderRowParams,
  SortableEntriesList
} from '@centreon/ui/components';

import { type FormikValues, useFormikContext } from 'formik';
import type { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { labelAddNewEntry } from '../ConfigurationBase/translatedLabels';
import { namedEntitiesListDecoder } from './api/decoders';
import type { NamedEntity } from './models';
import { labelEditTemplate, labelSelectATemplate } from './translatedLabels';

// A row added but not picked yet.
export type TemplateRow = NamedEntity | null;

// Where the template choices come from, and what the row's edit action opens.
export interface OrderedTemplatesConfig {
  baseEndpoint: string;
  getEndpoint: (parameters: {
    page?: number;
    search?: SearchParameter;
  }) => string;
  // The row's edit action; without it, the row has none.
  onEdit?: (id: number) => void;
  queryKey: string;
}

// As many rows as the design shows before the list scrolls.
const maxVisibleRows = 10;

const createTemplateRow = (): TemplateRow => null;

const isSameTemplate = (option: SelectEntry, value: SelectEntry): boolean =>
  option.id === value.id;

// The resource's templates, in the order they are inherited from: the first
// one prevails.
const OrderedTemplates = ({
  config: { baseEndpoint, getEndpoint, onEdit, queryKey },
  dataTestId,
  fieldName,
  getDisabled,
  label
}: InputPropsWithoutGroup & {
  config: OrderedTemplatesConfig;
}): ReactElement => {
  const { t } = useTranslation();
  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const templates = (values[fieldName] ?? []) as Array<TemplateRow>;
  const disabled = !!getDisabled?.(values);

  const changeTemplates = (nextTemplates: Array<TemplateRow>): void => {
    setFieldValue(fieldName, nextTemplates);
  };

  const renderRow = ({
    index,
    setValue,
    value,
    values: rows
  }: RenderRowParams<TemplateRow>): ReactElement => {
    // The server refuses a template twice; the other rows' are not offered.
    const pickedElsewhere = rows
      .filter((_, rowIndex) => rowIndex !== index)
      .map((row) => row?.id);

    return (
      <SingleConnectedAutocompleteField
        baseEndpoint={baseEndpoint}
        dataTestId={`${dataTestId}-${index}`}
        decoder={namedEntitiesListDecoder}
        disabled={disabled}
        field="name"
        fullWidth
        getEndpoint={getEndpoint}
        getOptionDisabled={(option: SelectEntry): boolean =>
          pickedElsewhere.includes(option.id as number)
        }
        isOptionEqualToValue={isSameTemplate}
        label={t(labelSelectATemplate)}
        onChange={(_event, template): void =>
          setValue((template as TemplateRow) ?? null)
        }
        queryKey={queryKey}
        value={value}
      />
    );
  };

  return (
    <div className="flex flex-col gap-2" data-testid={dataTestId}>
      <Typography className="text-base font-bold">{label}</Typography>
      {/* The shared list has no read-only mode: a disabled fieldset turns off
          its add, delete and drag buttons natively. */}
      <fieldset className="m-0 min-w-0 border-0 p-0" disabled={disabled}>
        <SortableEntriesList<TemplateRow>
          actions={({ value }) =>
            onEdit
              ? [
                  {
                    icon: <EditIcon />,
                    id: `${dataTestId}-edit`,
                    isDisabled: !value,
                    label: labelEditTemplate,
                    onClick: (): void => {
                      if (value) {
                        onEdit(value.id);
                      }
                    }
                  }
                ]
              : []
          }
          addLabel={t(labelAddNewEntry)}
          createValue={createTemplateRow}
          draggable={!disabled}
          label={label}
          maxVisibleRows={maxVisibleRows}
          onChange={changeTemplates}
          renderRow={renderRow}
          values={templates}
        />
      </fieldset>
    </div>
  );
};

export default OrderedTemplates;
