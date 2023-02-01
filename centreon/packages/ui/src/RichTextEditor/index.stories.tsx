import { useState } from 'react';

import { ComponentMeta } from '@storybook/react';
import { EditorState } from 'lexical';

import { Box } from '@mui/material';

import initialEditorState from './initialEditorState.json';

import RichTextEditor, { RichTextEditorProps } from '.';

export default {
  component: RichTextEditor,
  title: 'RichTextEditor'
} as ComponentMeta<typeof RichTextEditor>;

const Story = (props: RichTextEditorProps): JSX.Element => (
  <Box sx={{ backgroundColor: 'background.default', padding: 2 }}>
    <RichTextEditor {...props} />
  </Box>
);

export const normal = (): JSX.Element => <Story />;

export const withCustomEditorMinHeight = (): JSX.Element => (
  <Story minInputHeight={300} />
);

const StoryWithUpdateListener = (): JSX.Element => {
  const [editorState, setEditorState] = useState<EditorState>();

  return (
    <div>
      <Story getEditorState={setEditorState} />
      <pre>{JSON.stringify(editorState, null, 2)}</pre>
    </div>
  );
};

export const withUpdateListener = (): JSX.Element => (
  <StoryWithUpdateListener />
);

export const withInitialEditorState = (): JSX.Element => (
  <Story initialEditorState={JSON.stringify(initialEditorState)} />
);
