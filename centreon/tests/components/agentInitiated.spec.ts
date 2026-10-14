import { expect, test } from './fixtures';

// Migrated from AgentConfiguration/Form/ConnectionInitiated/AgentInitiated.
// The Cypress cases that only clicked around without assertion are covered by
// the token removal test, which checks the form values.
const storyId = 'ConnectionInitiated/AgentInitiated/Default';
const fields = [
  'Public certificate (.crt, .cert, .cer)',
  'CA (.crt, .cert, .cer)',
  'Private key (.key)'
];
const tokensField = 'Select existing CMA token(s)';
const emptyCertificates = {
  otelCaCertificate: '',
  otelPrivateKey: '',
  otelPublicCertificate: ''
};

test('renders empty, labelled certificate fields and the tokens', async ({
  expectThemedScreenshot,
  mount
}) => {
  const component = await mount(storyId);

  for (const label of fields) {
    const input = component.getByRole('textbox', { exact: true, name: label });
    await expect(input).toHaveValue('');
    await expect(input).toHaveAttribute('aria-label', label);
  }
  await expect(component.getByTestId(tokensField)).toBeVisible();
  await expect(component.getByTestId(tokensField).locator('..')).toContainText(
    '*'
  );

  await expectThemedScreenshot(component, 'agent-initiated');
});

test('renders the fields in error', async ({ mount }) => {
  const component = await mount(storyId, {
    configuration: { ...emptyCertificates, tokens: [] },
    withErrors: true
  });

  for (const label of [...fields, tokensField]) {
    await expect(component.getByTestId(label).first()).toBeAttached();
  }
});

const prefilled = [
  {
    configuration: {
      otelCaCertificate: 'existing-ca.cert',
      otelPrivateKey: 'existing-private.key',
      otelPublicCertificate: 'existing-public.crt',
      tokens: []
    },
    expected: [
      'existing-public.crt',
      'existing-ca.cert',
      'existing-private.key'
    ],
    name: 'saved values'
  },
  {
    configuration: {
      otelCaCertificate: null,
      otelPrivateKey: null,
      otelPublicCertificate: null,
      tokens: null
    },
    expected: ['', '', ''],
    name: 'null values'
  }
];

for (const { configuration, expected, name } of prefilled) {
  test(`renders ${name}`, async ({ mount }) => {
    const component = await mount(storyId, { configuration });

    for (const [index, label] of fields.entries()) {
      await expect(
        component.getByRole('textbox', { exact: true, name: label })
      ).toHaveValue(expected[index] as string);
    }
  });
}

test('edits and clears the certificate fields', async ({ mount }) => {
  const component = await mount(storyId);
  const values = ['test.cert', 'test_ca.cert', 'test.key'];

  for (const [index, label] of fields.entries()) {
    const input = component.getByRole('textbox', { exact: true, name: label });
    await input.fill(values[index] as string);
    await expect(input).toHaveValue(values[index] as string);
  }

  const publicCertificate = component.getByRole('textbox', {
    exact: true,
    name: fields[0]
  });
  await publicCertificate.clear();
  await expect(publicCertificate).toHaveValue('');
});

test('removes a selected token from its chip', async ({ mount }) => {
  // Known bug: AgentInitiated passes `ChipProps` to the @centreon/ui
  // autocomplete, which reads `chipProps`, so the chip delete icon is a no-op.
  // The Cypress spec clicked it without asserting the removal.
  test.fail(true, 'AgentInitiated passes ChipProps instead of chipProps');

  const component = await mount(storyId, {
    configuration: {
      ...emptyCertificates,
      tokens: [
        { id: 1, inputValue: 'input1', name: 'token1' },
        { id: 2, inputValue: 'input2', name: 'token2' },
        { id: 3, inputValue: 'input3', name: 'token3' }
      ]
    }
  });

  for (const index of [1, 2, 3]) {
    await expect(
      component.getByTestId(`tag-option-chip-${index}`)
    ).toContainText(`token${index}`);
  }

  await component
    .getByRole('button', { name: 'token1' })
    .getByTestId('CancelIcon')
    .click();

  await expect(component.getByTestId('tag-option-chip-1')).toHaveCount(0);
  await expect(component.getByTestId('form-values')).toHaveValue(
    /"name":"token2".*"name":"token3"/
  );
  await expect(component.getByTestId('form-values')).not.toHaveValue(
    /"name":"token1"/
  );
});
