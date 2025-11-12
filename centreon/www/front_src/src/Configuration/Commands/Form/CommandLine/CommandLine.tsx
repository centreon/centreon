import { useFormikContext } from 'formik';
import { ChangeEvent, ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';

import ArrowIcon from '@mui/icons-material/ArrowForwardSharp';

import { SingleConnectedAutocompleteField, TextField } from '@centreon/ui';
import { IconButton } from '@centreon/ui/components';

import { Command } from '../../models';

import {
  JSONLDEntitiesListDecoder,
  getGlobalMacrosEndpoint,
  getPluginsEndpoint,
  getStandardMacrosEndpoint
} from '../../api';

import {
  labelCommandLine,
  labelInsert,
  labelInstalledPlugins,
  labelPollerGlobalMacros,
  labelStandardMacros
} from '../../translatedLabels';

const CommandLine = (): ReactElement => {
  const { t } = useTranslation();

  const [macros, setMacros] = useState({
    globalMarco: null,
    standardMacro: null,
    installedPlugin: null
  });

  const { values, setFieldValue, setFieldTouched, touched, errors } =
    useFormikContext<Command>();

  const changeCommand = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldTouched('commandLine', true);
    setFieldValue('commandLine', event.target.value);
  };

  const changeMacro =
    (property: string) =>
    (_, value): void => {
      setMacros({ ...macros, [property]: value });
    };

  const insertMacroIntoCommand = (property: string) => (): void => {
    const macro = macros[property].name;
    const commandLine = values.commandLine;

    setFieldTouched('commandLine', true);
    setFieldValue('commandLine', `${commandLine}${macro}`);
  };

  return (
    <div className="grid grid-cols-[2fr_50px_5fr] pt-2">
      <div className="flex flex-col justify-between">
        <SingleConnectedAutocompleteField
          label={t(labelPollerGlobalMacros)}
          value={macros.globalMarco}
          getEndpoint={getGlobalMacrosEndpoint}
          onChange={changeMacro('globalMarco')}
          decoder={JSONLDEntitiesListDecoder}
          field="name"
        />

        <SingleConnectedAutocompleteField
          label={t(labelInstalledPlugins)}
          onChange={changeMacro('installedPlugin')}
          getEndpoint={getStandardMacrosEndpoint}
          value={macros.installedPlugin}
          decoder={JSONLDEntitiesListDecoder}
          field="name"
        />

        <SingleConnectedAutocompleteField
          label={t(labelStandardMacros)}
          onChange={changeMacro('standardMacro')}
          getEndpoint={getPluginsEndpoint}
          value={macros.standardMacro}
          decoder={JSONLDEntitiesListDecoder}
          field="name"
        />
      </div>
      <div className="flex flex-col justify-between items-end">
        <IconButton
          data-testid="Insert global marco"
          title={t(labelInsert)}
          onClick={insertMacroIntoCommand('globalMarco')}
          disabled={!macros.globalMarco}
          variant="ghost"
          icon={<ArrowIcon fontSize="small" />}
        />

        <IconButton
          data-testid="Insert installed plugin"
          title={t(labelInsert)}
          onClick={insertMacroIntoCommand('installedPlugin')}
          disabled={!macros.installedPlugin}
          variant="ghost"
          icon={<ArrowIcon fontSize="small" />}
        />

        <IconButton
          data-testid="Insert standard marco"
          title={t(labelInsert)}
          onClick={insertMacroIntoCommand('standardMacro')}
          disabled={!macros.standardMacro}
          variant="ghost"
          icon={<ArrowIcon fontSize="small" />}
        />
      </div>

      <TextField
        required
        multiline
        rows={6}
        value={values?.commandLine}
        onChange={changeCommand}
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
