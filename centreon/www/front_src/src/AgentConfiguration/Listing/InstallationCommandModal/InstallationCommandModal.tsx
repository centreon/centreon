import { Link, Typography } from '@mui/material';

import { equals } from 'ramda';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { IconButton, SingleConnectedAutocompleteField } from '@centreon/ui';
import { Modal } from '@centreon/ui/components';

import linuxIcon from '../../../assets/linux.png';
import windowsIcon from '../../../assets/windows.png';

import { getPollersEndpoint } from '../../api/endpoints';
import { CommandLine, Section, Warning } from './Components';
import { useInstallationCommand } from './useInstallationCommand';

import {
  labelCommandWarning,
  labelDownload,
  labelDownloadTheScript,
  labelExecuteTheScript,
  labelGenerateInstallationCommand,
  labelLinux,
  labelRunTheFollowingCommand,
  labelSelectOperatingSystem,
  labelSelectPoller,
  labelSelectPollerThatWillMonitor,
  labelThenCopyTheScript,
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
                getEndpoint={getPollersEndpoint}
                label={t(labelSelectPoller)}
                initialPage={1}
                value={poller}
                onChange={changePoller}
                field="name"
              />
            </div>
          </Section>

          <Section order={2} title={t(labelSelectOperatingSystem)}>
            <div className="flex gap-12 my-2">
              {[
                { name: Os.windows, label: labelWindows, src: windowsIcon },
                { name: Os.linux, label: labelLinux, src: linuxIcon }
              ].map(({ name, label, src }) => (
                <div key={label} className="flex flex-col gap-2.5 items-center">
                  <IconButton
                    className={`flex justify-center items-center w-16 h-16 rounded-sm ${equals(name, state.os) ? 'border-2 border-primary-main' : ''}`}
                    ariaLabel={t(label)}
                    onClick={() => setState({ ...state, os: name })}
                    title={t(label)}
                  >
                    <img src={src} alt={label} className="h-12 w-auto" />
                  </IconButton>
                  <Typography variant="subtitle2" className="font-medium">
                    {t(label)}
                  </Typography>
                </div>
              ))}
            </div>
          </Section>

          <Section order={3} title={t(labelDownloadTheScript)}>
            <Typography>
              <Link rel="noreferrer" target="_blank" href={state.scriptUrl}>
                {t(labelDownload)}
              </Link>
              &nbsp;
              {t(labelThenCopyTheScript)}
            </Typography>
          </Section>

          <Section order={4} title={t(labelExecuteTheScript)}>
            <div className="flex flex-col gap-1">
              <Typography>{t(labelRunTheFollowingCommand)}</Typography>
              <CommandLine commandLine={state.scriptCommand} />
            </div>
          </Section>
        </div>
      </Modal.Body>
    </Modal>
  );
};

export default InstallationCommandModal;
