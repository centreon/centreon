import type { Meta, StoryObj } from '@storybook/react';

import CopyCommand from './CopyCommand';

const meta: Meta<typeof CopyCommand> = {
  component: CopyCommand
};

export default meta;
type Story = StoryObj<typeof CopyCommand>;

export const Default: Story = {
  args: {
    language: 'yaml',
    text: 'key:\n    with:\n        input: "heyyy"'
  }
};

export const OneLine: Story = {
  args: {
    language: 'bash',
    text: 'echo "hello" | grep "hel"'
  }
};

export const WithCopyCommandIcon: Story = {
  args: {
    commandToCopy: 'echo "hello" | grep "hel"',
    language: 'bash',
    text: `# a simple command
echo "hello" | grep "hel"`
  }
};

export const UsingJson: Story = {
  args: {
    language: 'json',
    text: `{
  "number": 1,
  "boolean": true,
  "array": [
    {
      "string": "text"
    }
  ]
}`
  }
};

export const UsingPhp: Story = {
  args: {
    language: 'php',
    text: "<?php echo '<p>Hello World</p>'; ?>"
  }
};
