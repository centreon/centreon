import { LexicalComposer } from '@lexical/react/LexicalComposer';
import { RichTextPlugin } from '@lexical/react/LexicalRichTextPlugin';
import { HistoryPlugin } from '@lexical/react/LexicalHistoryPlugin';
import LexicalErrorBoundary from '@lexical/react/LexicalErrorBoundary';
import { AutoLinkNode, LinkNode } from '@lexical/link';
import anylogger from 'anylogger';
import { makeStyles } from 'tss-react/mui';
import { EditorState } from 'lexical';
import { LinkPlugin } from '@lexical/react/LexicalLinkPlugin';

import ContentEditable from './ContentEditable';
import ToolbarPlugin from './plugins/ToolbarPlugin/index';
import AutoCompleteLinkPlugin from './plugins/AutoLinkPlugin/index';
import FloatingLinkEditorPlugin from './plugins/FloatingLinkEditorPlugin';

export interface RichTextEditorProps {
  editable: boolean;
  editorStateForReadOnlyMode?: string;
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
  link: {
    color: theme.palette.primary.main
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
  editable = true,
  editorStateForReadOnlyMode
}: RichTextEditorProps): JSX.Element => {
  const { classes } = useStyles();

  const hasInitialTextContent = initialEditorState
    ? JSON.parse(initialEditorState).root?.children.length > 0
    : false;

  const initialConfig = {
    editable,
    editorState: initialEditorState,
    namespace,
    nodes: [AutoLinkNode, LinkNode],
    onError,
    theme: {
      link: classes.link,
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
            editable={editable}
            editorStateForReadOnlyMode={editorStateForReadOnlyMode}
            hasInitialTextContent={hasInitialTextContent}
            inputClassname={inputClassname}
            minInputHeight={minInputHeight}
            placeholder={placeholder}
          />
        }
        placeholder={null}
      />
      <HistoryPlugin />
      <LinkPlugin />
      <AutoCompleteLinkPlugin />
      <FloatingLinkEditorPlugin />
    </LexicalComposer>
  );
};
export default RichTextEditor;
