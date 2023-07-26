import { createStore } from 'jotai';

import { Module, RichTextEditor } from '@centreon/ui';

interface Options {
  text: string;
}
interface Props {
  panelOptions?: Options;
  store: ReturnType<typeof createStore>;
}

const GenericText = ({ panelOptions, store }: Props): JSX.Element => {
  return (
    <Module maxSnackbars={1} seedName="generic-text" store={store}>
      <RichTextEditor
        editable={false}
        initialEditorState={panelOptions?.text}
      />
    </Module>
  );
};

export default GenericText;
