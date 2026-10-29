import { Typography } from '@mui/material';

import { rs } from '@rstest/core';
import { interceptAPIRequest, Method, waitForRequest } from '@test-setup/api';
import { render } from '@test-setup/render';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { type FormikValues, useFormikContext } from 'formik';
import i18next from 'i18next';
import { path } from 'ramda';
import { initReactI18next } from 'react-i18next';
import { object } from 'yup';

import buildListingEndpoint from '../../api/buildListingEndpoint';
import TestQueryProvider from '../../api/TestQueryProvider';
import type { GetEndpointParams } from '../../InputField/Select/Autocomplete/Connected';
import { Form } from '../Form';
import { InputType } from './models';

const label = 'Icon';
const iconsEndpoint = '/configuration/icons';

const getIconUrl = (color: string): string =>
  `data:image/svg+xml,${encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="5" fill="${color}"/></svg>`
  )}`;

const icons = [
  { id: 1, name: 'blue', url: getIconUrl('blue') },
  { id: 2, name: 'green', url: getIconUrl('green') }
];

const getEndpoint = (parameters: GetEndpointParams): string =>
  buildListingEndpoint({ baseEndpoint: iconsEndpoint, parameters });

interface InitializeProps {
  dataTestId?: string;
  disabled?: boolean;
  fieldName?: string;
  initialValues?: FormikValues;
}

const ValueDisplay = ({ fieldName }: { fieldName: string }): JSX.Element => {
  const { values } = useFormikContext<FormikValues>();

  return (
    <Typography data-testid="value">
      {JSON.stringify(path(fieldName.split('.'), values) ?? null)}
    </Typography>
  );
};

const initialize = ({
  dataTestId,
  disabled = false,
  fieldName = 'icon',
  initialValues = { icon: null }
}: InitializeProps = {}): void => {
  interceptAPIRequest({
    alias: 'getIcons',
    method: Method.GET,
    path: `./api/latest${iconsEndpoint}**`,
    response: { meta: { limit: 10, page: 1, total: 2 }, result: icons }
  });

  render(
    <TestQueryProvider>
      <div style={{ width: 400 }}>
        <Form
          initialValues={initialValues}
          inputs={[
            {
              dataTestId,
              fieldName,
              getDisabled: () => disabled,
              group: '',
              iconSelector: { getEndpoint },
              label,
              type: InputType.IconSelector
            },
            {
              custom: {
                Component: () => <ValueDisplay fieldName={fieldName} />
              },
              fieldName: 'value',
              group: '',
              label: 'value',
              type: InputType.Custom
            }
          ]}
          submit={rs.fn()}
          validationSchema={object()}
        />
      </div>
    </TestQueryProvider>
  );
};

const getPreview = (name: string): HTMLElement | null =>
  document.querySelector(`img[alt="${name}"]:not([role="presentation"] img)`);

describe('Icon selector', () => {
  beforeEach(() => {
    expect.hasAssertions();

    i18next.use(initReactI18next).init({ lng: 'en', resources: {} });
  });

  it('lists the images of the endpoint, each with its thumbnail', async () => {
    initialize();

    await userEvent.click(await screen.findByTestId(label));
    await waitForRequest('@getIcons');

    const list = await screen.findByRole('listbox');

    await waitFor(() => {
      expect(within(list).getAllByRole('option')).toHaveLength(2);
    });
    icons.forEach(({ name, url }) => {
      const thumbnail = within(list).getByAltText(name);

      expect(thumbnail).toHaveAttribute('src', url);
      expect(thumbnail).toHaveAttribute('width', '20');
    });
  });

  it('stores the picked image in the field and shows it beside the picker', async () => {
    initialize();

    await userEvent.click(await screen.findByTestId(label));
    await waitForRequest('@getIcons');
    await userEvent.click(await screen.findByText('green'));

    await waitFor(() => {
      expect(screen.getByTestId('value')).toHaveTextContent(
        JSON.stringify(icons[1])
      );
    });
    expect(screen.getByTestId(label)).toHaveValue('green');
    await waitFor(() => {
      expect(getPreview('green')).toHaveAttribute('src', icons[1].url);
    });
  });

  it('shows the initial image and removes it once the field is cleared', async () => {
    initialize({ initialValues: { icon: icons[0] } });

    expect(await screen.findByTestId(label)).toHaveValue('blue');
    await waitFor(() => {
      expect(getPreview('blue')).toBeInTheDocument();
    });

    await userEvent.hover(screen.getByTestId(label));
    await userEvent.click(await screen.findByTitle('Clear'));

    await waitFor(() => {
      expect(screen.getByTestId('value')).toHaveTextContent('null');
    });
    expect(getPreview('blue')).not.toBeInTheDocument();
  });

  it('reads and writes a nested field', async () => {
    initialize({
      fieldName: 'extendedInfos.icon',
      initialValues: { extendedInfos: { icon: icons[0] } }
    });

    expect(await screen.findByTestId(label)).toHaveValue('blue');

    await userEvent.click(screen.getByTestId(label));
    await waitForRequest('@getIcons');
    await userEvent.click(await screen.findByText('green'));

    await waitFor(() => {
      expect(screen.getByTestId('value')).toHaveTextContent(
        JSON.stringify(icons[1])
      );
    });
  });

  it('uses the given test id instead of the label', async () => {
    initialize({ dataTestId: 'icon-selector' });

    expect(await screen.findByTestId('icon-selector')).toBeVisible();
    expect(screen.queryByTestId(label)).not.toBeInTheDocument();
  });

  it('is disabled when getDisabled says so', async () => {
    initialize({ disabled: true, initialValues: { icon: icons[0] } });

    expect(await screen.findByTestId(label)).toBeDisabled();
  });
});
