import { Typography } from '@mui/material';

import { IconButton, SingleConnectedAutocompleteField } from '@centreon/ui';
import { Modal } from '@centreon/ui/components';

import { equals } from 'ramda';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { CommandLine, Section, Warning } from './Components';
import { useInstallationCommand } from './useInstallationCommand';

import linuxIcon from '../../../assets/linux.png';
import windowsIcon from '../../../assets/windows.png';
import { getPollersEndpoint } from '../../api/endpoints';

import {
  labelCommandWarning,
  labelExecuteTheScript,
  labelGenerateInstallationCommand,
  labelLinux,
  labelRunTheFollowingCommand,
  labelSelectOperatingSystem,
  labelSelectPoller,
  labelSelectPollerThatWillMonitor,
  labelWindows
} from '../../translatedLabels';

enum Os {
  windows = 'windows',
  linux = 'linux'
}

const InstallationCommandModal = (): ReactElement => {
  const { t } = useTranslation();

  const { isOpen, close, state, setState, changePoller, poller } =
    useInstallationCommand();

  return (
    <Modal onClose={close} open={isOpen} size="medium">
      <Modal.Header>{t(labelGenerateInstallationCommand)}</Modal.Header>
      <Modal.Body>
        <div className="mb-6">
          <Warning label={t(labelCommandWarning)} />
        </div>

        <div className="flex flex-col gap-4">
          <Section order={1} title={t(labelSelectPollerThatWillMonitor)}>
            <div className="my-2">
              <SingleConnectedAutocompleteField
                field="name"
                getEndpoint={getPollersEndpoint}
                initialPage={1}
                label={t(labelSelectPoller)}
                onChange={changePoller}
                value={poller}
              />
            </div>
          </Section>

          <Section order={2} title={t(labelSelectOperatingSystem)}>
            <div className="flex gap-12 my-2">
              {[
                { label: labelWindows, name: Os.windows, src: windowsIcon },
                { label: labelLinux, name: Os.linux, src: linuxIcon }
              ].map(({ name, label, src }) => (
                <div className="flex flex-col gap-2.5 items-center" key={label}>
                  <IconButton
                    ariaLabel={t(label)}
                    className={`flex justify-center items-center w-16 h-16 rounded-sm ${equals(name, state.os) ? 'border-2 border-primary-main' : ''}`}
                    onClick={() => setState({ ...state, os: name })}
                  >
                    <img alt={label} className="h-12 w-auto" src={src} />
                  </IconButton>

                  <Typography
                    className="font-medium cursor-pointer"
                    variant="subtitle2"
                    onClick={() => setState({ ...state, os: name })}
                  >
                    {t(label)}
                  </Typography>
                </div>
              ))}
            </div>
          </Section>
          {
            <Section order={4} title={t(labelExecuteTheScript)}>
              {state.scriptCommand && (
                <div className="flex flex-col gap-1">
                  <Typography>{t(labelRunTheFollowingCommand)}</Typography>
                  <CommandLine commandLine={state.scriptCommand} />
                </div>
              )}
            </Section>
          }
        </div>
      </Modal.Body>
    </Modal>
  );
};

export default InstallationCommandModal;
