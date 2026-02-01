import ArrowIcon from '@mui/icons-material/ArrowForwardSharp';
import HelpOutlineIcon from '@mui/icons-material/HelpOutline';

import { SingleConnectedAutocompleteField, TextField } from '@centreon/ui';
import { IconButton, Tooltip } from '@centreon/ui/components';

import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { useUserPermissions } from '../../useUserPermissions';
import TooltipContent from './TooltipContent';
import { useCommandLine } from './useCommandLine';

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

  const { canEdit } = useUserPermissions();

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
          decoder={JSONLDEntitiesListDecoder}
          field="name"
          getEndpoint={getGlobalMacrosEndpoint}
          label={t(labelPollerGlobalMacros)}
          onChange={changeMacro('globalMarco')}
          value={macros.globalMarco}
          disabled={!canEdit}
        />
        <SingleConnectedAutocompleteField
          decoder={JSONLDEntitiesListDecoder}
          field="name"
          getEndpoint={getPluginsEndpoint}
          label={t(labelInstalledPlugins)}
          onChange={changeMacro('installedPlugin')}
          value={macros.installedPlugin}
          disabled={!canEdit}
        />
        <SingleConnectedAutocompleteField
          decoder={JSONLDEntitiesListDecoder}
          field="name"
          getEndpoint={getStandardMacrosEndpoint}
          label={t(labelStandardMacros)}
          onChange={changeMacro('standardMacro')}
          value={macros.standardMacro}
          disabled={!canEdit}
        />
      </div>
      <div className="flex flex-column justify-center items-center pl-2">
        {macros.installedPlugin && (
          <Tooltip
            arrow
            classes={{
              tooltip:
                'relative w-60 min-h-30 p-0 text-text-primary bg-background-paper shadow-md'
            }}
            followCursor={false}
            label={<TooltipContent name={macros.installedPlugin.name} />}
          >
            <HelpOutlineIcon color="primary" fontSize="small" />
          </Tooltip>
        )}
      </div>
      <div className="flex flex-col justify-between items-end">
        <IconButton
          data-testid="Insert global marco"
          disabled={!macros.globalMarco || !canEdit}
          icon={<ArrowIcon fontSize="small" />}
          onClick={insertMacroIntoCommand('globalMarco')}
          title={t(labelInsert)}
          variant="ghost"
        />
        <IconButton
          data-testid="Insert installed plugin"
          disabled={!macros.installedPlugin || !canEdit}
          icon={<ArrowIcon fontSize="small" />}
          onClick={insertMacroIntoCommand('installedPlugin')}
          title={t(labelInsert)}
          variant="ghost"
        />
        <IconButton
          data-testid="Insert standard marco"
          disabled={!macros.standardMacro || !canEdit}
          icon={<ArrowIcon fontSize="small" />}
          onClick={insertMacroIntoCommand('standardMacro')}
          title={t(labelInsert)}
          variant="ghost"
        />
      </div>
      <TextField
        dataTestId={labelCommandLine}
        error={error}
        fullWidth
        inputRef={textFieldRef}
        label={t(labelCommandLine)}
        multiline
        onChange={changeCommand}
        required
        rows={6}
        textFieldSlotsAndSlotProps={{
          slotProps: {
            htmlInput: {
              'aria-label': t(labelCommandLine)
            }
          }
        }}
        value={values?.commandLine}
        disabled={!canEdit}
      />
    </div>
  );
};

export default CommandLine;
