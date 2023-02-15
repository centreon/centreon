import { useState } from 'react';

import { ComponentMeta } from '@storybook/react';
import { EditorState } from 'lexical';

import RichTextEditor from './RichTextEditor';
import type { RichTextEditorProps } from './RichTextEditor';
import initialEditorState from './initialEditorState.json';

export default {
  argTypes: {
    editable: { control: false },
    initialEditorState: { control: 'text' },
    inputClassname: { control: 'text' },
    minInputHeight: { control: 'number' },
    namespace: { control: 'text' },
    placeholder: { control: 'text' }
  },
  component: RichTextEditor,
  title: 'RichTextEditor'
} as ComponentMeta<typeof RichTextEditor>;

const Template = (props: RichTextEditorProps): JSX.Element => (
  <RichTextEditor {...props} />
);

export const normal = Template.bind({});

export const withCustomEditorMinHeight = Template.bind({});
withCustomEditorMinHeight.args = {
  minInputHeight: 300
};

const StoryWithUpdateListener = (): JSX.Element => {
  const [editorState, setEditorState] = useState<EditorState>();

  return (
    <div>
      <Template getEditorState={setEditorState} />
      <pre>{JSON.stringify(editorState, null, 2)}</pre>
    </div>
  );
};

export const withUpdateListener = (): JSX.Element => (
  <StoryWithUpdateListener />
);

export const withInitialEditorState = Template.bind({});
withInitialEditorState.args = {
  initialEditorState: JSON.stringify(initialEditorState)
};

export const withCustomPlaceholder = Template.bind({});
withCustomPlaceholder.args = {
  placeholder: 'Custom placeholder...'
};

export const withEditableFalse = Template.bind({});
withEditableFalse.args = {
  editable: false,
  initialEditorState: JSON.stringify(initialEditorState)
};

const StoryWithEditableFalseLikePreview = (): JSX.Element => {
  const [editorState, setEditorState] = useState<EditorState>();

  return (
    <div>
      <Template getEditorState={setEditorState} namespace="editable" />
      <Template
        editable={false}
        editorStateForReadOnlyMode={JSON.stringify(editorState)}
        namespace="uneditable"
        placeholder=""
      />
    </div>
  );
};

export const withEditableFalseLikePreview = (): JSX.Element => (
  <StoryWithEditableFalseLikePreview />
);
