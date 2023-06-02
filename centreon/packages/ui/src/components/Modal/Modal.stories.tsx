import { Meta, StoryObj } from '@storybook/react';

import { Button } from '../Button';

import { Modal } from '.';

const meta: Meta<typeof Modal> = {
  argTypes: {
    size: {
      control: {
        type: 'select'
      },
      options: ['small', 'medium', 'large']
    }
  },
  component: Modal,
  parameters: {
    chromatic: { delay: 300 }
  }
};

export default meta;
type Story = StoryObj<typeof Modal>;

export const Default: Story = {
  args: {
    hasCloseButton: true,
    open: true,
    size: 'small'
  },
  render: (args) => (
    <Modal {...args}>
      <Modal.Header>Modal title</Modal.Header>
      <Modal.Body>
        <p>
          Occaecat consectetur amet officia magna. Eu sunt aute duis duis cillum
          irure mollit ex aute excepteur eu id cillum.
        </p>
      </Modal.Body>
      <Modal.Actions
        labels={{
          cancel: 'Cancel',
          confirm: 'Confirm'
        }}
      />
    </Modal>
  )
};

export const AsDangerAction: Story = {
  args: {
    ...Default.args
  },
  render: (args) => (
    <Modal {...args}>
      <Modal.Body>
        <Modal.Body>
          <p>
            Occaecat consectetur amet <strong>officia magna</strong>. Eu sunt
            aute duis duis cillum irure mollit ex aute excepteur eu id cillum.
          </p>
        </Modal.Body>
      </Modal.Body>
      <Modal.Actions
        isDanger
        labels={{
          cancel: 'Cancel',
          confirm: 'Confirm'
        }}
      />
    </Modal>
  )
};

export const WithCustomAction: Story = {
  args: {
    ...Default.args
  },
  render: (args) => (
    <Modal {...args}>
      <Modal.Header>Modal title</Modal.Header>
      <Modal.Body>
        <Modal.Body>
          <p>
            Occaecat consectetur amet <strong>officia magna</strong>. Eu sunt
            aute duis duis cillum irure mollit ex aute excepteur eu id cillum.
          </p>
        </Modal.Body>
      </Modal.Body>
      <Modal.Actions>
        <Button size="small" variant="primary">
          OK
        </Button>
      </Modal.Actions>
    </Modal>
  )
};

export const AsPassive: Story = {
  args: {
    ...Default.args
  },
  render: (args) => (
    <Modal {...args}>
      <Modal.Header>Modal title</Modal.Header>
      <Modal.Body>
        <p>
          Occaecat consectetur amet officia magna. Eu sunt aute duis duis cillum
          irure mollit ex aute excepteur eu id cillum.
        </p>
      </Modal.Body>
    </Modal>
  )
};
