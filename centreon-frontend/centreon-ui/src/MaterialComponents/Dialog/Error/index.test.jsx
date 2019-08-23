/* eslint-disable no-undef */

import React from 'react';
import { render } from '@testing-library/react';
import ErrorDialog from '.';

describe('ErrorDialog', () => {
  it('renders', async () => {
    const { container, findByText } = render(
      <ErrorDialog
        active
        title="Error"
        info="Something unexpected happened..."
        confirmLabel="Close"
        onClose={() => {}}
      />,
    );

    await findByText('Error');

    // TODO this provides an empty Snapshot. This is probably due to the Fade animation not handled by react-testing-library.
    expect(container.firstChild).toMatchSnapshot();
  });
});
