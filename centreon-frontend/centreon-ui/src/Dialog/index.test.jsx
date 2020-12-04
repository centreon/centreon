import React from 'react';

import { render } from '@testing-library/react';

import Dialog from '.';

describe('Dialog', () => {
  it('overrides message', () => {
    const { getByText } = render(
      <Dialog
        open
        labelTitle="title"
        labelCancel="cancel"
        labelConfirm="confirm"
        onCancel={jest.fn()}
        onConfirm={jest.fn()}
      >
        message
      </Dialog>,
    );

    expect(getByText('title')).toBeInTheDocument();
    expect(getByText('message')).toBeInTheDocument();
    expect(getByText('cancel')).toBeInTheDocument();
    expect(getByText('confirm')).toBeInTheDocument();
  });
});
