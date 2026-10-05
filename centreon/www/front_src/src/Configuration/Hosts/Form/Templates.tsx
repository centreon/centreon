import EditIcon from '@mui/icons-material/Edit';
import { Typography } from '@mui/material';

import {
  type InputPropsWithoutGroup,
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

import {
  getFormHostTemplatesEndpoint,
  hostsBaseEndpoint
} from '../api/endpoints';
import { namedEntitiesListDecoder } from '../api/namedEntityDecoders';
import type { NamedEntity } from '../models';
import {
  labelAddNewEntry,
  labelEditTemplate,
  labelHostTemplate
} from '../translatedLabels';
import { getHostTemplateConfigurationUrl } from '../utils';

// A row added but not picked yet.
export type TemplateRow = NamedEntity | null;

// As many rows as the design shows before the list scrolls.
const maxVisibleRows = 10;

const createTemplateRow = (): TemplateRow => null;

// The field's search parameters are wider than what the selector reads.
const getEndpoint = (parameters: { page: number; search?: unknown }): string =>
  getFormHostTemplatesEndpoint(
    parameters as Parameters<typeof getFormHostTemplatesEndpoint>[0]
  );

const isSameTemplate = (option: SelectEntry, value: SelectEntry): boolean =>
  option.id === value.id;

// The host's templates, in the order they are inherited from: the first one
// prevails.
const Templates = ({
  dataTestId,
  fieldName,
  getDisabled,
  label
}: InputPropsWithoutGroup): ReactElement => {
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
        baseEndpoint={hostsBaseEndpoint}
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
        label={t(labelHostTemplate)}
        onChange={(_event, template): void =>
          setValue((template as TemplateRow) ?? null)
        }
        queryKey="host-form-templates"
        value={value}
      />
    );
  };

  return (
    <div className="flex flex-col gap-2" data-testid={dataTestId}>
      {/* MUI's own font weight would beat a Tailwind class. */}
      <Typography fontWeight="bold" variant="body1">
        {label}
      </Typography>
      {/* The shared list has no read-only mode: a disabled fieldset turns off
          its add, delete and drag buttons natively. */}
      <fieldset className="m-0 min-w-0 border-0 p-0" disabled={disabled}>
        <SortableEntriesList<TemplateRow>
          actions={({ value }) => [
            {
              icon: <EditIcon />,
              id: `${dataTestId}-edit`,
              isDisabled: !value,
              label: labelEditTemplate,
              // A new tab, so the form being filled is not lost.
              onClick: (): void => {
                if (value) {
                  window.open(
                    getHostTemplateConfigurationUrl(value.id),
                    '_blank',
                    'noopener,noreferrer'
                  );
                }
              }
            }
          ]}
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

export default Templates;
