import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';

import {
  labelHost,
  labelName,
  labelScheduling,
  labelTemplate
} from '../translatedLabels';
import initialize from './initialize';
import { iconUrl } from './utils';

const getRow = (serviceName: string): HTMLElement =>
  screen.getByText(serviceName).closest('[role="row"]') as HTMLElement;

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllServices');
  await screen.findByText('cpu');
};

export default () => {
  describe('Columns: ', () => {
    it('displays every column of the listing', async () => {
      await initializeListing();

      [labelHost, labelName, labelScheduling, labelTemplate].forEach(
        (label) => {
          expect(screen.getAllByText(label)[0]).toBeVisible();
        }
      );
    });

    it('shows the host on every row, even when consecutive rows share it', async () => {
      await initializeListing();

      expect(within(getRow('cpu')).getByText('host A')).toBeVisible();
      expect(within(getRow('disk')).getByText('host A')).toBeVisible();
      expect(within(getRow('memory')).getByText('host B')).toBeVisible();
    });

    it('links the host to its configuration form', async () => {
      await initializeListing();

      expect(screen.getByTestId('service-host-link_0')).toHaveAttribute(
        'href',
        '/main.php?p=60101&o=c&host_id=10'
      );
    });

    it('renders the icons returned by the API', async () => {
      await initializeListing();

      await waitFor(() => {
        expect(screen.getByTestId('server.png')).toHaveAttribute(
          'src',
          iconUrl
        );
        expect(screen.getByTestId('cpu.png')).toHaveAttribute('src', iconUrl);
      });
    });

    it('falls back to the default icons when neither has one', async () => {
      await initializeListing();

      const row = getRow('memory');

      expect(within(row).getByTestId('HostIcon')).toBeInTheDocument();
      expect(within(row).getByTestId('ServiceIcon')).toBeInTheDocument();
    });

    it('shows the intervals in minutes when they fall on whole minutes', async () => {
      await initializeListing();

      expect(within(getRow('cpu')).getByText('5 min / 1 min')).toBeVisible();
    });

    it('shows the intervals in seconds otherwise', async () => {
      await initializeListing({ intervalLength: 45 });

      expect(
        within(getRow('disk')).getByText('135 sec / 90 sec')
      ).toBeVisible();
    });

    it('shows a dash for an interval no template sets', async () => {
      await initializeListing();

      expect(within(getRow('memory')).getByText('- / -')).toBeVisible();
    });

    it('links the direct template to its configuration page', async () => {
      await initializeListing();

      const template = screen.getByTestId('service-template-link_30');

      expect(template).toHaveTextContent(/^generic-active-service$/);
      expect(template).toHaveAttribute(
        'href',
        '/main.php?p=60206&o=c&service_id=30'
      );

      expect(
        getRow('memory').querySelector(
          '[data-testid^="service-template-link_"]'
        )
      ).toBeNull();
    });
  });
};
