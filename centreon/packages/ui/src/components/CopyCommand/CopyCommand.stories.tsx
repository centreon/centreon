import { Meta, StoryObj } from '@storybook/react';
import CopyCommand from './CopyCommand';

const meta: Meta<typeof CopyCommand> = {
  component: CopyCommand
};

export default meta;
type Story = StoryObj<typeof CopyCommand>;

export const Default: Story = {
  args: {
    text: 'key:\n    with:\n        input: "heyyy"',
    language: 'yaml'
  }
};

export const OneLine: Story = {
  args: {
    text: 'echo "hello" | grep "hel"',
    language: 'bash'
  }
};

export const WithCopyCommandIcon: Story = {
  args: {
    text: `# a simple command
echo "hello" | grep "hel"`,
    language: 'bash',
    commandToCopy: 'echo "hello" | grep "hel"'
  }
};

export const UsingJson: Story = {
  args: {
    text: `{
  "number": 1,
  "boolean": true,
  "array": [
    {
      "string": "text"
    }
  ]
}`,
    language: 'json'
  }
};

export const UsingPhp: Story = {
  args: {
    text: "<?php echo '<p>Hello World</p>'; ?>",
    language: 'php'
  }
};
