import { Link, Typography } from '@mui/material';
import { useAtom } from 'jotai';
import { ReactElement, useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import { SingleConnectedAutocompleteField } from '@centreon/ui';

import { Modal } from '@centreon/ui/components';

import LinuxIcon from '../../../assets/linux.png';
import windowsIcon from '../../../assets/windows.png';

import { pollerToGenerateCommanAtom } from '../../atoms';

// import { useGenerateInstallationCommand } from '../hooks/useGenerateInstallationCommand';

import { CommandLine, Section, Warning } from './Components';

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

const InstallationCommandModal = (): ReactElement => {
  const { t } = useTranslation();

  const [poller, setPoller] = useAtom(pollerToGenerateCommanAtom);

  const isOpen = Boolean(poller);

  const close = useCallback(() => {
    setPoller(null);
  }, []);

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
                getEndpoint={() => '/hello'}
                label={t(labelSelectPoller)}
                initialPage={1}
                value={{ id: 1, name: 'poller_1' }}
                onChange={() => undefined}
                field="name"
              />
            </div>
          </Section>

          <Section order={2} title={t(labelSelectOperatingSystem)}>
            <div className="flex gap-12 my-2">
              <div className="flex flex-col gap-2.5 items-center">
                <img
                  src={windowsIcon}
                  alt={labelWindows}
                  className="h-12 w-auto"
                />
                <Typography variant="subtitle2" className="font-medium">
                  {labelWindows}
                </Typography>
              </div>
              <div className="flex flex-col gap-2.5 items-center">
                <img src={LinuxIcon} alt={labelLinux} className="h-12 w-auto" />
                <Typography variant="subtitle2" className="font-medium">
                  {labelLinux}
                </Typography>
              </div>
            </div>
          </Section>

          <Section order={3} title={t(labelDownloadTheScript)}>
            <Typography>
              <Link
                rel="noreferrer"
                target="_blank"
                href="https://github.com/centreon/centreon-collect/releases/download/centreon-monitoring-agent-25.10.1/centreon-monitoring-agent-25.10.1.exe"
              >
                {t(labelDownload)}
              </Link>
              &nbsp;
              {t(labelThenCopyTheScript)}
            </Typography>
          </Section>

          <Section order={4} title={t(labelExecuteTheScript)}>
            <div className="flex flex-col gap-1">
              <Typography>{t(labelRunTheFollowingCommand)}</Typography>
              <CommandLine commandLine="installcma.ps /FINGERPRINT=XXXXXXX  /COMPONENTS=agent,plugins /HOST=host_1 /ENDPOINT=https://central/centreon:4317" />
            </div>
          </Section>
        </div>
      </Modal.Body>
    </Modal>
  );
};

export default InstallationCommandModal;
