import { LinearProgress, Typography } from '@mui/material';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import {
  CommandLine,
  Section
} from '../../../../../AgentConfiguration/Listing/InstallationCommandModal/Components';
import InstallCommandLogo from '../../../../../assets/InstallCommand.svg';

import { generatedCommandAtom } from './atoms';
import type { CloudInstallCommandFormValues } from './models';

import { Button } from '@centreon/ui/components';
import {
  labelClickToGenerate,
  labelCopyTheFollowingCommand,
  labelGenerateInstallationCommand,
  labelGeneratingCommand
} from '../../../translatedLabels';

const CommandSection = (): ReactElement => {
  const { t } = useTranslation();
  const generatedCommand = useAtomValue(generatedCommandAtom);

  const { isSubmitting, isValid, dirty } =
    useFormikContext<CloudInstallCommandFormValues>();

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
            <Button size="small" disabled={isSubmitting || !isValid || !dirty}>
              <img
                alt="Install command"
                className="w-7 h-7"
                src={InstallCommandLogo}
              />
            </Button>
            <div className="flex flex-col gap-2 flex-1">
              <Typography variant="body2">{t(labelClickToGenerate)}</Typography>
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
