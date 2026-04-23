import { LinearProgress, Typography } from '@mui/material';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import {
  CommandLine,
  Section
} from '../../../../../AgentConfiguration/Listing/InstallationCommandModal/Components';
import centreonLogo from '../../../../../assets/logo-centreon-colors.svg';
import {
  labelClickToGenerate,
  labelCommandGenerationStep,
  labelCopyTheFollowingCommand,
  labelGenerateInstallationCommand,
  labelGeneratingCommand
} from '../../../translatedLabels';
import { generatedCommandAtom } from './atoms';
import type { CloudInstallCommandFormValues } from './models';

const CommandSection = (): ReactElement => {
  const { t } = useTranslation();
  const generatedCommand = useAtomValue(generatedCommandAtom);
  const { isSubmitting } = useFormikContext<CloudInstallCommandFormValues>();

  return (
    <Section order={4} title={t(labelGenerateInstallationCommand)}>
      <div className="flex flex-col gap-2 my-2">
        {generatedCommand ? (
          <div className="flex flex-col gap-1">
            <Typography>{t(labelCopyTheFollowingCommand)}</Typography>
            <CommandLine commandLine={generatedCommand} />
          </div>
        ) : (
          <div className="flex items-start gap-3">
            <img
              alt="Centreon"
              className="w-10 h-10"
              src={centreonLogo}
            />
            <div className="flex flex-col gap-2 flex-1">
              <Typography variant="body2">
                {t(labelClickToGenerate)}
              </Typography>
              {isSubmitting && (
                <>
                  <LinearProgress />
                  <Typography color="text.secondary" variant="caption">
                    {t(labelGeneratingCommand)}
                  </Typography>
                </>
              )}
            </div>
          </div>
        )}
      </div>
    </Section>
  );
};

export default CommandSection;
