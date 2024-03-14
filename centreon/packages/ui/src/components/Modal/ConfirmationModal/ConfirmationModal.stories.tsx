import { Meta, StoryObj } from '@storybook/react';
import { atom, useSetAtom, createStore, Provider } from 'jotai';

import { Button } from '../../Button';

import ConfirmationModal from './ConfirmationModal';

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
      <ConfirmationModal {...args} />
    </>
  );
};

export const Default: Story = {
  args: {
    hasCloseButton: true,
    labels: {
      title: 'Title',
      description: 'Description',
      cancel: 'Cancel',
      confirm:'Confirm'
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
      title: (data) => `Hello ${data}`,
      description: (data) => `Hello ${data} from description`,
      cancel: 'Cancel',
      confirm:'Confirm'
    }
  },
  render: (args) => (
    <Provider store={store}>
      <Component {...args} atom={testAtom} />
    </Provider>
  )
};
