import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { object } from 'yup';

import { Form } from '../Form';
import { InputType } from './models';

const initialize = (): void => {
  render(
    <Form
      initialValues={{ community: '', note: '' }}
      inputs={[
        {
          dataTestId: 'note',
          fieldName: 'note',
          group: '',
          label: 'Note',
          type: InputType.Text
        },
        {
          dataTestId: 'community',
          fieldName: 'community',
          group: '',
          label: 'SNMP community',
          type: InputType.Password
        }
      ]}
      submit={rs.fn()}
      validationSchema={object()}
    />
  );
};

const getInput = async (testId: string): Promise<HTMLInputElement> =>
  (await screen.findAllByTestId(testId)).find(
    (element) => element.tagName === 'INPUT'
  ) as HTMLInputElement;

describe('Password input', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it('asks the browser and the password managers not to fill it', async () => {
    initialize();

    const password = await getInput('community');

    expect(password).toHaveAttribute('type', 'password');
    expect(password).toHaveAttribute('autocomplete', 'new-password');
    expect(password).toHaveAttribute('data-1p-ignore', 'true');
    expect(password).toHaveAttribute('data-bwignore', 'true');
    expect(password).toHaveAttribute('data-lpignore', 'true');
  });

  it('keeps those hints while the password is shown', async () => {
    initialize();

    await userEvent.click(
      await screen.findByRole('button', { name: /password|show|visib/i })
    );

    const password = await getInput('community');

    expect(password).toHaveAttribute('type', 'text');
    expect(password).toHaveAttribute('autocomplete', 'new-password');
    expect(password).toHaveAttribute('data-1p-ignore', 'true');
  });

  it('leaves the other text inputs as they were', async () => {
    initialize();

    const note = await getInput('note');

    expect(note).toHaveAttribute('autocomplete', 'off');
    expect(note).not.toHaveAttribute('data-1p-ignore');
  });
});
