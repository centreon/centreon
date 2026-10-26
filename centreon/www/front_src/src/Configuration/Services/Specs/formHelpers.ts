import { Method } from '@centreon/ui';

import { interceptAPIRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import {
  serviceFormHostsEndpoint,
  serviceFormServiceTemplatesEndpoint
} from '../Form/sections/serviceConfiguration';
import {
  getServiceFormHostsResponse,
  getServiceFormServiceTemplatesResponse
} from './utils';

const getPopper = (): HTMLElement =>
  document.querySelector('.MuiAutocomplete-popper') as HTMLElement;

const pick = async (testId: string, option: string): Promise<void> => {
  const field = await screen.findByTestId(testId);

  await userEvent.click(field);
  await waitFor(() => expect(getPopper()).not.toBeNull());
  await userEvent.click(await within(getPopper()).findByText(option));
  await waitFor(() => expect(field).toHaveValue(option));
};

// A service is created with a name and a host or not at all, and on a cloud
// platform with a template too, so a section's create test fills them before
// it saves. Called once the add form is open.
export const fillRequiredServiceFields = async ({
  withTemplate = false
}: {
  withTemplate?: boolean;
} = {}): Promise<void> => {
  interceptAPIRequest({
    alias: 'getServiceFormHosts',
    method: Method.GET,
    path: `**${serviceFormHostsEndpoint}?**`,
    response: getServiceFormHostsResponse()
  });
  interceptAPIRequest({
    alias: 'getServiceFormServiceTemplates',
    method: Method.GET,
    path: `**${serviceFormServiceTemplatesEndpoint}?**`,
    response: getServiceFormServiceTemplatesResponse()
  });

  const [, name] = await screen.findAllByTestId('service-form-name');

  await userEvent.type(name as HTMLInputElement, 'new service');
  await pick('service-form-host', 'host A');

  if (withTemplate) {
    await pick('service-form-template', 'generic-active-service');
  }
};
