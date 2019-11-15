import React from 'react';
import { act, render, fireEvent } from '@testing-library/react';
import Wizard, { Page } from '.';

function renderWizard() {
  return render(
    <Wizard open>
      <Page label="step label 1">
        <div>Step 1</div>
      </Page>
      <Page label="step label 2">
        <div>Step 2</div>
      </Page>
      <Page label="step label 3">
        <div>Step 3</div>
      </Page>
    </Wizard>,
  );
}

describe('Wizard', () => {
  it('displays step labels', () => {
    const { getByText } = renderWizard();

    expect(getByText('step label 1')).toBeInTheDocument();
    expect(getByText('step label 2')).toBeInTheDocument();
    expect(getByText('step label 3')).toBeInTheDocument();
  });

  it('goes to next and previous steps', async () => {
    const { getByText } = renderWizard();

    await act(async () => {
      fireEvent.click(getByText('Next').parentNode);
    });

    expect(getByText('Step 2')).toBeInTheDocument();

    await act(async () => {
      fireEvent.click(getByText('Previous').parentNode);
    });

    expect(getByText('Step 1')).toBeInTheDocument();
  });
});
