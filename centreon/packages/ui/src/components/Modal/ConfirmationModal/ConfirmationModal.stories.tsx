import { Meta, StoryObj } from '@storybook/react';
import { Provider, atom, createStore, useSetAtom } from 'jotai';

import { Button } from '../../Button';

import { ConfirmationModal } from './ConfirmationModal';

const meta: Meta<typeof ConfirmationModal> = {
  component: ConfirmationModal
};

export default meta;
type Story = StoryObj<typeof ConfirmationModal>;

const testAtom = atom<string | null>(null);

const store = createStore();

const Component = (args): JSX.Element => {
  const setAtom = useSetAtom(testAtom);

  return (
    <>
      <Button onClick={() => setAtom('John')}>Click to open modal</Button>
      <ConfirmationModal<string> {...args} />
    </>
  );
};

export const Default: Story = {
  args: {
    hasCloseButton: true,
    labels: {
      cancel: 'Cancel',
      confirm: 'Confirm',
      description: 'Description',
      title: 'Title'
    }
  },
  render: (args) => (
    <Provider store={store}>
      <Component {...args} atom={testAtom} />
    </Provider>
  )
};

export const WithDynamicLabels: Story = {
  args: {
    hasCloseButton: true,
    labels: {
      cancel: 'Cancel',
      confirm: 'Confirm',
      description: (data) => `Hello ${data} from description`,
      title: (data) => `Hello ${data}`
    }
  },
  render: (args) => (
    <Provider store={store}>
      <Component {...args} atom={testAtom} />
    </Provider>
  )
};
