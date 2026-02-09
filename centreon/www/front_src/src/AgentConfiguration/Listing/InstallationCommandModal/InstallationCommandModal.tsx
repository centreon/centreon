import { Link, Typography } from '@mui/material';
import { useAtom } from 'jotai';
import { ReactElement, useCallback } from 'react';
import { useTranslation } from 'react-i18next';

import { IconButton, SingleConnectedAutocompleteField } from '@centreon/ui';

import { Modal } from '@centreon/ui/components';

import linuxIcon from '../../../assets/linux.png';
import windowsIcon from '../../../assets/windows.png';

import { pollerToGenerateCommanAtom } from '../../atoms';

// import { useGenerateInstallationCommand } from '../hooks/useGenerateInstallationCommand';

import { CommandLine, Section, Warning } from './Components';

import { pick } from 'ramda';
import { commandLine, scriptURL } from '../../Specs/utils';
import { getPollersEndpoint } from '../../api/endpoints';
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

  const changePoller = (_, value): void => {
    const selectedPoller = value ? pick(['id', 'name'], value) : {};

    setPoller(selectedPoller);
  };

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
                { label: labelWindows, src: windowsIcon },
                { label: labelLinux, src: linuxIcon }
              ].map(({ label, src }) => (
                <div key={label} className="flex flex-col gap-2.5 items-center">
                  <IconButton
                    className={`flex justify-center items-center w-16 h-16 rounded-sm ${label === labelWindows ? 'border-2 border-primary-main' : ''}`}
                    ariaLabel={t(label)}
                    onClick={() => undefined}
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
              <Link rel="noreferrer" target="_blank" href={scriptURL}>
                {t(labelDownload)}
              </Link>
              &nbsp;
              {t(labelThenCopyTheScript)}
            </Typography>
          </Section>

          <Section order={4} title={t(labelExecuteTheScript)}>
            <div className="flex flex-col gap-1">
              <Typography>{t(labelRunTheFollowingCommand)}</Typography>
              <CommandLine commandLine={commandLine} />
            </div>
          </Section>
        </div>
      </Modal.Body>
    </Modal>
  );
};

export default InstallationCommandModal;
