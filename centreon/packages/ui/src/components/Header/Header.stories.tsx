import { Meta, StoryObj } from '@storybook/react';
import { Header } from './Header';

const meta: Meta<typeof Header> = {
  component: Header,
}

export default meta;
type Story = StoryObj<typeof Header>

export const Default: Story = {
  args: {
    title: 'Header',
  }
}

export const WithNav: Story = {
  args: {
    title: 'Header with nav',
    nav: <>
      <a href="#">Home</a>
      <a href="#">Other</a>
    </>,
  }
}