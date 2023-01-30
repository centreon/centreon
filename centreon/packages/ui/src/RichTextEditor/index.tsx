import { LexicalComposer } from '@lexical/react/LexicalComposer';
import { RichTextPlugin } from '@lexical/react/LexicalRichTextPlugin';
import { HistoryPlugin } from '@lexical/react/LexicalHistoryPlugin';
import LexicalErrorBoundary from '@lexical/react/LexicalErrorBoundary';
import anylogger from 'anylogger';
import { makeStyles } from 'tss-react/mui';
import { EditorState } from 'lexical';
import { gt } from 'ramda';

import ContentEditable from './ContentEditable';
import ToolbarPlugin from './Plugins/ToolbarPlugin';

export interface RichTextEditorProps {
  getEditorState?: (editorState: EditorState) => void;
  initialEditorState?: string;
  inputClassname?: string;
  minInputHeight?: number;
  namespace?: string;
  placeholder?: string;
}

const log = anylogger('Rich text editor');

const onError = (error: Error): void => {
  log.error(error.message);
};

const useStyles = makeStyles()({
  strikethough: {
    textDecoration: 'line-through'
  },
  underline: {
    textDecoration: 'underline'
  },
  underlineStrikethrough: {
    textDecoration: 'underline line-through'
  }
});

const RichTextEditor = ({
  namespace = 'RichTextEditor',
  minInputHeight = 100,
  inputClassname,
  placeholder = 'Type here...',
  getEditorState,
  initialEditorState
}: RichTextEditorProps): JSX.Element => {
  const { classes } = useStyles();

  const hasInitialTextContent =
    initialEditorState &&
    gt(JSON.parse(initialEditorState).root?.children.length, 0);

  const initialConfig = {
    editorState: initialEditorState,
    namespace,
    onError,
    theme: {
      text: {
        strikethrough: classes.strikethough,
        underline: classes.underline,
        underlineStrikethrough: classes.underlineStrikethrough
      }
    }
  };

  return (
    <LexicalComposer initialConfig={initialConfig}>
      <ToolbarPlugin getEditorState={getEditorState} />
      <RichTextPlugin
        ErrorBoundary={LexicalErrorBoundary}
        contentEditable={
          <ContentEditable
            hasInitialTextContent={hasInitialTextContent}
            inputClassname={inputClassname}
            minInputHeight={minInputHeight}
            placeholder={placeholder}
          />
        }
        placeholder={null}
      />
      <HistoryPlugin />
    </LexicalComposer>
  );
};

export default RichTextEditor;
