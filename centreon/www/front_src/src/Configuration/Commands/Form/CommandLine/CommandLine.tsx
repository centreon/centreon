import { SingleConnectedAutocompleteField, TextField } from '@centreon/ui';
import { IconButton, Tooltip } from '@centreon/ui/components';

import ArrowIcon from '@mui/icons-material/ArrowForwardSharp';
import HelpOutlineIcon from '@mui/icons-material/HelpOutline';

import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';
import {
  JSONLDEntitiesListDecoder,
  getGlobalMacrosEndpoint,
  getPluginsEndpoint,
  getStandardMacrosEndpoint
} from '../../api';

import { useCommandLine } from './useCommandLine';

import {
  labelCommandLine,
  labelEnableShellSyntaxTooltip,
  labelInsert,
  labelInstalledPlugins,
  labelPollerGlobalMacros,
  labelStandardMacros
} from '../../translatedLabels';

const CommandLine = (): ReactElement => {
  const { t } = useTranslation();

  const {
    macros,
    changeMacro,
    changeCommand,
    insertMacroIntoCommand,
    textFieldRef,
    values,
    error
  } = useCommandLine();

  return (
    <div className="grid grid-cols-[2fr_20px_40px_5fr] pt-2">
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
          getEndpoint={getPluginsEndpoint}
          value={macros.installedPlugin}
          decoder={JSONLDEntitiesListDecoder}
          field="name"
        />
        <SingleConnectedAutocompleteField
          label={t(labelStandardMacros)}
          onChange={changeMacro('standardMacro')}
          getEndpoint={getStandardMacrosEndpoint}
          value={macros.standardMacro}
          decoder={JSONLDEntitiesListDecoder}
          field="name"
        />
      </div>
      <div className="flex flex-column justify-center items-center pl-2">
        <Tooltip label={t(labelEnableShellSyntaxTooltip)}>
          <HelpOutlineIcon fontSize="small" color="primary" />
        </Tooltip>
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
        inputRef={textFieldRef}
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
        error={error}
      />
    </div>
  );
};

export default CommandLine;
