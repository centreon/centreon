import type { Meta, StoryObj } from '@storybook/react';
import { HttpResponse, http } from 'msw';
import { object } from 'yup';

import buildListingEndpoint from '../../api/buildListingEndpoint';
import type { GetEndpointParams } from '../../InputField/Select/Autocomplete/Connected';
import { Form } from '../Form';
import { InputType } from './models';

const getIconUrl = (color: string): string =>
  `data:image/svg+xml,${encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="5" fill="${color}"/></svg>`
  )}`;

const icons = [
  { id: 1, name: 'blue', url: getIconUrl('#1976d2') },
  { id: 2, name: 'green', url: getIconUrl('#2e7d32') },
  { id: 3, name: 'red', url: getIconUrl('#d32f2f') }
];

const iconsEndpoint = '/icons';

const getEndpoint = (parameters: GetEndpointParams): string =>
  buildListingEndpoint({ baseEndpoint: iconsEndpoint, parameters });

interface Args {
  disabled: boolean;
  icon: (typeof icons)[number] | null;
}

const IconSelectorForm = ({ disabled, icon }: Args): JSX.Element => (
  <div style={{ width: 400 }}>
    <Form
      initialValues={{ icon }}
      inputs={[
        {
          fieldName: 'icon',
          getDisabled: () => disabled,
          group: '',
          iconSelector: { getEndpoint },
          label: 'Icon',
          type: InputType.IconSelector
        }
      ]}
      submit={() => undefined}
      validationSchema={object()}
    />
  </div>
);

const meta: Meta<typeof IconSelectorForm> = {
  args: { disabled: false, icon: null },
  component: IconSelectorForm,
  parameters: {
    docs: {
      description: {
        component:
          '`InputType.IconSelector`: picks an image from the listing returned by `iconSelector.getEndpoint`, showing each option with its thumbnail and the picked image beside the field. The field (`fieldName`, a dotted path for a nested value) holds the picked `{ id, name, url }`, or null.'
      }
    },
    msw: {
      handlers: [
        http.get(`**${iconsEndpoint}**`, () =>
          HttpResponse.json({
            meta: { limit: 10, page: 1, total: icons.length },
            result: icons
          })
        )
      ]
    }
  },
  title: 'Form/Inputs/IconSelector'
};

export default meta;
type Story = StoryObj<typeof IconSelectorForm>;

export const Empty: Story = {};

export const WithIcon: Story = { args: { icon: icons[1] } };

export const Disabled: Story = { args: { disabled: true, icon: icons[2] } };
