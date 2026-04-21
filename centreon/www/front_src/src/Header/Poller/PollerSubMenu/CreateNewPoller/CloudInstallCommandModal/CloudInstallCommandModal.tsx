import ComputerIcon from '@mui/icons-material/Computer';
import { CircularProgress, TextField, Typography } from '@mui/material';

import { IconButton, SingleConnectedAutocompleteField } from '@centreon/ui';
import { Button, Modal } from '@centreon/ui/components';

import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { PollerEnvironment } from '../../models';

import { useCloudInstallCommand } from './useCloudInstallCommand';

import {
  CommandLine,
  Section
} from '../../../../../AgentConfiguration/Listing/InstallationCommandModal/Components';
import { getTokensEndpoint } from '../../../../../AgentConfiguration/api/endpoints';
import { listTokensDecoder } from '../../../../../AuthenticationTokens/api';

import {
  labelCopyInstallCommand,
  labelCopyTheFollowingCommand,
  labelDocker,
  labelEnterPollerName,
  labelGenerate,
  labelGenerateAndCopyCommand,
  labelGeneratingCommand,
  labelPollerName,
  labelSelectPollerEnvironment,
  labelSelectPollerToken,
  labelSelectToken,
  labelVM,
  labelValidate
} from '../../../translatedLabels';

const dockerIcon = (
  <svg fill="currentColor" height="48" viewBox="0 0 24 24" width="48">
    <path d="M13.983 11.078h2.119a.186.186 0 00.186-.185V9.006a.186.186 0 00-.186-.186h-2.119a.185.185 0 00-.185.185v1.888c0 .102.083.185.185.185m-2.954-5.43h2.118a.186.186 0 00.186-.186V3.574a.186.186 0 00-.186-.185h-2.118a.185.185 0 00-.185.185v1.888c0 .102.082.185.185.186m0 2.716h2.118a.187.187 0 00.186-.186V6.29a.186.186 0 00-.186-.185h-2.118a.185.185 0 00-.185.185v1.887c0 .102.082.186.185.186m-2.93 0h2.12a.186.186 0 00.184-.186V6.29a.185.185 0 00-.185-.185H8.1a.185.185 0 00-.185.185v1.887c0 .102.083.186.185.186m-2.964 0h2.119a.186.186 0 00.185-.186V6.29a.186.186 0 00-.185-.185H5.136a.186.186 0 00-.186.185v1.887c0 .102.084.186.186.186m5.893 2.715h2.118a.186.186 0 00.186-.185V9.006a.186.186 0 00-.186-.186h-2.118a.185.185 0 00-.185.185v1.888c0 .102.082.185.185.185m-2.93 0h2.12a.185.185 0 00.184-.185V9.006a.185.185 0 00-.184-.186h-2.12a.185.185 0 00-.184.185v1.888c0 .102.083.185.185.185m-2.964 0h2.119a.185.185 0 00.185-.185V9.006a.186.186 0 00-.185-.186H5.136a.186.186 0 00-.186.186v1.887c0 .102.084.185.186.185m-2.92 0h2.12a.185.185 0 00.184-.185V9.006a.186.186 0 00-.184-.186h-2.12a.185.185 0 00-.184.185v1.888c0 .102.082.185.185.185M23.763 9.89c-.065-.051-.672-.51-1.954-.51-.338.001-.676.03-1.01.087-.248-1.7-1.653-2.53-1.716-2.566l-.344-.199-.226.327c-.284.438-.49.922-.612 1.43-.23.97-.09 1.882.403 2.661-.595.332-1.55.413-1.744.42H.751a.751.751 0 00-.75.748 11.376 11.376 0 00.692 4.062c.545 1.428 1.355 2.48 2.41 3.124 1.18.723 3.1 1.137 5.275 1.137.983.003 1.963-.086 2.93-.266a12.248 12.248 0 003.823-1.389c.98-.567 1.86-1.288 2.61-2.136 1.252-1.418 1.998-2.997 2.553-4.4h.221c1.372 0 2.215-.549 2.68-1.009.309-.293.55-.65.707-1.046l.098-.288Z" />
  </svg>
);

const CloudInstallCommandModal = (): ReactElement => {
  const { t } = useTranslation();

  const {
    isOpen,
    close,
    state,
    canGenerate,
    generate,
    isGenerating,
    isExporting,
    setPollerName,
    setEnvironment,
    setToken,
    validate
  } = useCloudInstallCommand();

  const isReadOnly = state.isGenerated;

  return (
    <Modal onClose={close} open={isOpen} size="medium">
      <Modal.Header>{t(labelCopyInstallCommand)}</Modal.Header>
      <Modal.Body>
        <div className="flex flex-col gap-4">
          <Section order={1} title={t(labelEnterPollerName)}>
            <div className="my-2">
              <TextField
                data-testid="cloud-poller-name"
                disabled={isReadOnly}
                fullWidth
                label={t(labelPollerName)}
                onChange={(e) => setPollerName(e.target.value)}
                required
                size="small"
                value={state.pollerName}
              />
            </div>
          </Section>

          <Section order={2} title={t(labelSelectPollerEnvironment)}>
            <div className="flex gap-12 my-2">
              {[
                {
                  env: PollerEnvironment.VM,
                  icon: <ComputerIcon className="h-12 w-12" />,
                  label: labelVM
                },
                {
                  env: PollerEnvironment.Docker,
                  icon: dockerIcon,
                  label: labelDocker
                }
              ].map(({ env, icon, label }) => (
                <div className="flex flex-col gap-2.5 items-center" key={label}>
                  <IconButton
                    ariaLabel={t(label)}
                    className={`flex justify-center items-center w-16 h-16 rounded-sm ${state.environment === env ? 'border-2 border-primary-main' : ''}`}
                    data-selected={state.environment === env}
                    disabled={isReadOnly}
                    onClick={() => setEnvironment(env)}
                  >
                    {icon}
                  </IconButton>
                  <Typography
                    className="font-medium cursor-pointer"
                    onClick={() => !isReadOnly && setEnvironment(env)}
                    variant="subtitle2"
                  >
                    {t(label)}
                  </Typography>
                </div>
              ))}
            </div>
          </Section>

          <Section order={3} title={t(labelSelectPollerToken)}>
            <div className="my-2">
              <SingleConnectedAutocompleteField
                decoder={listTokensDecoder}
                disabled={isReadOnly}
                field="token_name"
                getEndpoint={getTokensEndpoint}
                label={t(labelSelectToken)}
                onChange={setToken}
                required
                value={state.token}
              />
            </div>
          </Section>

          <Section order={4} title={t(labelGenerateAndCopyCommand)}>
            <div className="flex flex-col gap-2 my-2">
              {!state.isGenerated && (
                <div>
                  <Button
                    data-testid="generate-command"
                    disabled={!canGenerate || isGenerating}
                    onClick={generate}
                    size="medium"
                  >
                    {isGenerating ? (
                      <div className="flex items-center gap-2">
                        <CircularProgress color="inherit" size={16} />
                        {t(labelGeneratingCommand)}
                      </div>
                    ) : (
                      t(labelGenerate)
                    )}
                  </Button>
                </div>
              )}

              {state.generatedCommand && (
                <div className="flex flex-col gap-1">
                  <Typography>{t(labelCopyTheFollowingCommand)}</Typography>
                  <CommandLine commandLine={state.generatedCommand} />
                </div>
              )}
            </div>
          </Section>
        </div>
      </Modal.Body>
      {state.isGenerated && (
        <Modal.Actions
          disabled={isExporting}
          labels={{
            cancel: undefined,
            confirm: t(labelValidate)
          }}
          onConfirm={validate}
        />
      )}
    </Modal>
  );
};

export default CloudInstallCommandModal;
