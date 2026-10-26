import { Method } from '@centreon/ui';

import { interceptAPIRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { serviceFormHostsEndpoint } from '../Form/sections/serviceConfiguration';
import { getServiceFormHostsResponse } from './utils';

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

// A service is created with a name and a host or not at all, so a section's
// create test fills both before it saves. Called once the add form is open.
export const fillRequiredServiceFields = async (): Promise<void> => {
  interceptAPIRequest({
    alias: 'getServiceFormHosts',
    method: Method.GET,
    path: `**${serviceFormHostsEndpoint}?**`,
    response: getServiceFormHostsResponse()
  });

  const [, name] = await screen.findAllByTestId('service-form-name');

  await userEvent.type(name as HTMLInputElement, 'new service');

  const host = await screen.findByTestId('service-form-host');

  await userEvent.click(host);
  await waitFor(() => expect(getPopper()).not.toBeNull());
  await userEvent.click(await within(getPopper()).findByText('host A'));
  await waitFor(() => expect(host).toHaveValue('host A'));
};
