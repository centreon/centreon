import { LinearProgress, Typography } from '@mui/material';

import { InstallCommandIcon } from '@centreon/ui';
import { Button } from '@centreon/ui/components';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import {
  CommandLine,
  Section
} from '../../../../../../AgentConfiguration/Listing/InstallationCommandModal/Components';
import {
  labelClickToGenerate,
  labelCommandGenerationStep,
  labelCopyTheFollowingCommand,
  labelGenerateInstallationCommand
} from '../../../../translatedLabels';
import { generatedCommandAtom } from '../../atoms';
import type { CloudInstallCommandFormValues } from '../../models';

const CommandSection = (): ReactElement => {
  const { t } = useTranslation();
  const generatedCommand = useAtomValue(generatedCommandAtom);

  const { isSubmitting, isValid, dirty, submitForm } =
    useFormikContext<CloudInstallCommandFormValues>();

  return (
    <Section order={4} title={t(labelGenerateInstallationCommand)}>
      {generatedCommand ? (
        <div className="flex flex-col gap-1">
          <Typography variant="body2">
            {t(labelCopyTheFollowingCommand)}
          </Typography>
          <CommandLine commandLine={generatedCommand} />
        </div>
      ) : (
        <div className="flex flex-col gap-2 my-2">
          <div className="flex items-start gap-3">
            <Button
              disabled={isSubmitting || !isValid || !dirty}
              onClick={submitForm}
              size="small"
            >
              <InstallCommandIcon className="w-7 h-7" />
            </Button>
            <Typography variant="body2">{t(labelClickToGenerate)}</Typography>
          </div>
          {isSubmitting && (
            <div className="flex flex-col gap-2">
              <LinearProgress />
              <CommandLine
                commandLine={generatedCommand || ''}
                defaultMessage={labelCommandGenerationStep}
              />
            </div>
          )}
        </div>
      )}
    </Section>
  );
};

export default CommandSection;
