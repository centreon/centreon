import { Typography } from '@mui/material';

import { useAtomValue } from 'jotai';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { CommandLine } from '../../../../../AgentConfiguration/Listing/InstallationCommandModal/Components';
import { labelCopyTheFollowingCommand } from '../../../translatedLabels';
import { generatedCommandAtom } from './atoms';

const CommandSection = (): ReactElement | null => {
  const { t } = useTranslation();
  const generatedCommand = useAtomValue(generatedCommandAtom);

  if (!generatedCommand) {
    return null;
  }

  return (
    <div className="flex flex-col gap-1">
      <Typography>{t(labelCopyTheFollowingCommand)}</Typography>
      <CommandLine commandLine={generatedCommand} />
    </div>
  );
};

export default CommandSection;
