import { LexicalComposer } from '@lexical/react/LexicalComposer';
import { RichTextPlugin } from '@lexical/react/LexicalRichTextPlugin';
import { HistoryPlugin } from '@lexical/react/LexicalHistoryPlugin';
import LexicalErrorBoundary from '@lexical/react/LexicalErrorBoundary';
import anylogger from 'anylogger';
import { makeStyles } from 'tss-react/mui';
import { EditorState } from 'lexical';

import ContentEditable from './ContentEditable';
import ToolbarPlugin from './plugins/ToolbarPlugin/index';

export interface RichTextEditorProps {
  editable: boolean;
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

const useStyles = makeStyles()((theme) => ({
  bold: {
    fontWeight: theme.typography.fontWeightBold
  },
  italic: {
    fontStyle: 'italic'
  },
  strikethough: {
    textDecoration: 'line-through'
  },
  underline: {
    textDecoration: 'underline'
  },
  underlineStrikethrough: {
    textDecoration: 'underline line-through'
  }
}));

const RichTextEditor = ({
  namespace = 'RichTextEditor',
  minInputHeight = 100,
  inputClassname,
  placeholder = 'Type here...',
  getEditorState,
  initialEditorState,
  editable = true
}: RichTextEditorProps): JSX.Element => {
  const { classes } = useStyles();

  const hasInitialTextContent = initialEditorState
    ? JSON.parse(initialEditorState).root?.children.length > 0
    : false;

  const initialConfig = {
    editable,
    editorState: initialEditorState,
    namespace,
    onError,
    theme: {
      text: {
        bold: classes.bold,
        italic: classes.italic,
        strikethrough: classes.strikethough,
        underline: classes.underline,
        underlineStrikethrough: classes.underlineStrikethrough
      }
    }
  };

  return (
    <LexicalComposer initialConfig={initialConfig}>
      <ToolbarPlugin editable={editable} getEditorState={getEditorState} />
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
