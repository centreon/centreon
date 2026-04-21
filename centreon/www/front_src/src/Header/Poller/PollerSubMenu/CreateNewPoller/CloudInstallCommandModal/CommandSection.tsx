import { Typography } from '@mui/material';

import { useAtomValue } from 'jotai';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import {
  CommandLine,
  Section
} from '../../../../../AgentConfiguration/Listing/InstallationCommandModal/Components';
import {
  labelCopyTheFollowingCommand,
  labelGenerateAndCopyCommand
} from '../../../translatedLabels';
import { generatedCommandAtom } from './atoms';

const CommandSection = (): ReactElement => {
  const { t } = useTranslation();
  const generatedCommand = useAtomValue(generatedCommandAtom);

  return (
    <Section order={4} title={t(labelGenerateAndCopyCommand)}>
      <div className="flex flex-col gap-2 my-2">
        {generatedCommand && (
          <div className="flex flex-col gap-1">
            <Typography>{t(labelCopyTheFollowingCommand)}</Typography>
            <CommandLine commandLine={generatedCommand} />
          </div>
        )}
      </div>
    </Section>
  );
};

export default CommandSection;
