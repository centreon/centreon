import { Meta, StoryObj } from '@storybook/react';
import { http, HttpResponse } from 'msw';
import Pagination from '.';
import { generateItems } from './utils';

const mockedListing = {
  result: generateItems(6),
  meta: {
    page: 1,
    total: 35,
    limit: 6
  }
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
          width: '240px',
          background: '#EDEDED'
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
