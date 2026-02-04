import type { Meta, StoryObj } from '@storybook/react';
import { HttpResponse, http } from 'msw';

import Pagination from '.';
import { generateItems } from './utils';

const mockedListing = {
  meta: {
    limit: 6,
    page: 1,
    total: 35
  },
  result: generateItems(6)
};

const meta: Meta<typeof Pagination> = {
  args: {},
  component: Pagination,
  parameters: {
    msw: {
      handlers: [
        http.get('**/listing**', () => {
          return HttpResponse.json(mockedListing);
        })
      ]
    }
  },
  render: (args) => {
    return (
      <div
        style={{
          background: '#EDEDED',
          width: '240px'
        }}
      >
        <Pagination {...args} />
      </div>
    );
  }
};

export default meta;
type Story = StoryObj<typeof Pagination>;

export const Default: Story = {
  args: { api: { baseEndpoint: '/test/listing', queryKey: ['pagination'] } }
};
