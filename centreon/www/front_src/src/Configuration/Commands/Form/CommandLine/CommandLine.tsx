import { useFormikContext } from 'formik';
import { ChangeEvent, ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import ArrowIcon from '@mui/icons-material/ArrowForwardSharp';

import { SingleConnectedAutocompleteField, TextField } from '@centreon/ui';
import { IconButton } from '@centreon/ui/components';

import { Command } from '../../models';

import {
  getGlobalMacrosEndpoint,
  getPluginsEndpoint,
  getStandardMacrosEndpoint
} from '../../api';

import {
  labelCommandLine,
  labelInstalledPlugins,
  labelPollerGlobalMacros,
  labelStandardMacros
} from '../../translatedLabels';

const CommandLine = (): ReactElement => {
  const { t } = useTranslation();

  const { values, setFieldValue, setFieldTouched, touched, errors } =
    useFormikContext<Command>();

  const value = values?.commandLine;

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldTouched('commandLine', true);
    setFieldValue('commandLine', event.target.value);
  };

  return (
    <div className="grid grid-cols-[2fr_50px_5fr] pt-2">
      <div className="flex flex-col justify-between">
        <SingleConnectedAutocompleteField
          label={t(labelPollerGlobalMacros)}
          onChange={() => undefined}
          getEndpoint={getGlobalMacrosEndpoint}
          value={null}
        />

        <SingleConnectedAutocompleteField
          label={t(labelInstalledPlugins)}
          onChange={() => undefined}
          getEndpoint={getStandardMacrosEndpoint}
          value={null}
        />

        <SingleConnectedAutocompleteField
          label={t(labelStandardMacros)}
          onChange={() => undefined}
          getEndpoint={getPluginsEndpoint}
          value={null}
        />
      </div>
      <div className="flex flex-col justify-between">
        <div className="flex justify-end items-center">
          <IconButton
            data-testid="Insert"
            title="Insert"
            onClick={() => undefined}
            disabled={false}
            variant="ghost"
            icon={<ArrowIcon fontSize="small" />}
          />
        </div>
        <div className="flex justify-end items-center">
          <IconButton
            data-testid="Insert"
            title="Insert"
            onClick={() => undefined}
            disabled={true}
            variant="ghost"
            icon={<ArrowIcon fontSize="small" />}
          />
        </div>
        <div className="flex justify-end items-center">
          <IconButton
            data-testid="Insert"
            title="Insert"
            onClick={() => undefined}
            disabled={true}
            variant="ghost"
            icon={<ArrowIcon fontSize="small" />}
          />
        </div>
      </div>

      <TextField
        required
        multiline
        rows={6}
        value={value}
        onChange={change}
        label={t(labelCommandLine)}
        dataTestId={labelCommandLine}
        fullWidth
        textFieldSlotsAndSlotProps={{
          slotProps: {
            htmlInput: {
              'aria-label': t(labelCommandLine)
            }
          }
        }}
        error={touched?.commandLine && errors?.commandLine}
      />
    </div>
  );
};

export default CommandLine;
