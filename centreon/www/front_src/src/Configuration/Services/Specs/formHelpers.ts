import { Method } from '@centreon/ui';

import { interceptAPIRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { serviceFormServiceTemplatesEndpoint } from '../Form/sections/checkOptions';
import { serviceFormHostsEndpoint } from '../Form/sections/generalInformation';
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

// A service is created with a name, a host, and a check command or a template
// that brings one (on a cloud platform the template is required), so a
// section's create test fills them before it saves. A template covers both
// rules; a test of the check command passes `withTemplate: false`. Called once
// the add form is open.
export const fillRequiredServiceFields = async ({
  withTemplate = true
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
