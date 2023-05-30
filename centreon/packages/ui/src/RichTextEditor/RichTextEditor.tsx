import { LexicalComposer } from '@lexical/react/LexicalComposer';
import { RichTextPlugin } from '@lexical/react/LexicalRichTextPlugin';
import { HistoryPlugin } from '@lexical/react/LexicalHistoryPlugin';
import LexicalErrorBoundary from '@lexical/react/LexicalErrorBoundary';
import { AutoLinkNode, LinkNode } from '@lexical/link';
import anylogger from 'anylogger';
import { makeStyles } from 'tss-react/mui';
import { EditorState } from 'lexical';
import { LinkPlugin } from '@lexical/react/LexicalLinkPlugin';
import { equals } from 'ramda';

import { Typography } from '@mui/material';

import ContentEditable from './ContentEditable';
import ToolbarPlugin from './plugins/ToolbarPlugin/index';
import AutoCompleteLinkPlugin from './plugins/AutoLinkPlugin/index';
import FloatingLinkEditorPlugin from './plugins/FloatingLinkEditorPlugin';

export interface RichTextEditorProps {
  cententClassName?: string;
  editable: boolean;
  editorState?: string;
  error?: string;
  getEditorState?: (editorState: EditorState) => void;
  initialEditorState?: string;
  inputClassname?: string;
  minInputHeight?: number;
  namespace?: string;
  onBlur?: (e: string) => void;
  placeholder?: string;
  resetEditorToInitialStateCondition?: () => boolean;
  toolbarPositions?: 'start' | 'end';
}

const log = anylogger('Rich text editor');

const onError = (error: Error): void => {
  log.error(error.message);
};

const useStyles = makeStyles<{ toolbarPositions: 'start' | 'end' }>()(
  (theme, { toolbarPositions }) => ({
    bold: {
      fontWeight: theme.typography.fontWeightBold
    },
    container: equals(toolbarPositions, 'end')
      ? {
          display: 'flex',
          flexDirection: 'column-reverse'
        }
      : {},
    error: {
      color: theme.palette.error.main,
      fontSize: theme.spacing(1.5),
      fontWeight: '200',
      paddingLeft: theme.spacing(1.5),
      paddingTop: theme.spacing(0.5)
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
    toolbar: equals(toolbarPositions, 'end')
      ? {
          marginTop: theme.spacing(0.5)
        }
      : {},
    underline: {
      textDecoration: 'underline'
    },
    underlineStrikethrough: {
      textDecoration: 'underline line-through'
    }
  })
);

const RichTextEditor = ({
  namespace = 'RichTextEditor',
  minInputHeight = 100,
  inputClassname,
  placeholder = 'Type here...',
  getEditorState,
  initialEditorState,
  editable = true,
  editorState,
  resetEditorToInitialStateCondition,
  toolbarPositions = 'start',
  error,
  onBlur,
  cententClassName
}: RichTextEditorProps): JSX.Element => {
  const { classes } = useStyles({ toolbarPositions });

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
      <div className={classes.container}>
        <div className={classes.toolbar}>
          <ToolbarPlugin editable={editable} getEditorState={getEditorState} />
        </div>
        <div>
          <RichTextPlugin
            ErrorBoundary={LexicalErrorBoundary}
            contentEditable={
              <ContentEditable
                className={cententClassName || ''}
                editable={editable}
                editorState={editorState}
                error={error}
                hasInitialTextContent={hasInitialTextContent}
                initialEditorState={initialEditorState}
                inputClassname={inputClassname}
                minInputHeight={minInputHeight}
                namespace={namespace}
                placeholder={placeholder}
                resetEditorToInitialStateCondition={
                  resetEditorToInitialStateCondition
                }
                onBlur={onBlur}
              />
            }
            placeholder={null}
          />
          <HistoryPlugin />
          <LinkPlugin />
          <AutoCompleteLinkPlugin />
          <FloatingLinkEditorPlugin editable={editable} />
          {error && <Typography className={classes.error}>{error}</Typography>}
        </div>
      </div>
    </LexicalComposer>
  );
};
export default RichTextEditor;
