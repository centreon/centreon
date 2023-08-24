import { LexicalComposer } from '@lexical/react/LexicalComposer';
import { RichTextPlugin } from '@lexical/react/LexicalRichTextPlugin';
import { HistoryPlugin } from '@lexical/react/LexicalHistoryPlugin';
import LexicalErrorBoundary from '@lexical/react/LexicalErrorBoundary';
import { AutoLinkNode, LinkNode } from '@lexical/link';
import { HeadingNode } from '@lexical/rich-text';
import { ListItemNode, ListNode } from '@lexical/list';
import anylogger from 'anylogger';
import { makeStyles } from 'tss-react/mui';
import { EditorState, LexicalEditor } from 'lexical';
import { LinkPlugin } from '@lexical/react/LexicalLinkPlugin';
import { equals } from 'ramda';
import { ListPlugin } from '@lexical/react/LexicalListPlugin';
import { OnChangePlugin } from '@lexical/react/LexicalOnChangePlugin';

import { Typography } from '@mui/material';

import ContentEditable from './ContentEditable';
import ToolbarPlugin from './plugins/ToolbarPlugin/index';
import AutoCompleteLinkPlugin from './plugins/AutoLinkPlugin/index';
import FloatingLinkEditorPlugin from './plugins/FloatingLinkEditorPlugin';

export interface RichTextEditorProps {
  contentClassName?: string;
  disabled?: boolean;
  displayMacrosButton?: boolean;
  editable: boolean;
  editorState?: string;
  error?: string;
  getEditorState?: (editorState: EditorState, editor: LexicalEditor) => void;
  initialEditorState?: string;
  inputClassname?: string;
  minInputHeight?: number;
  namespace?: string;
  onBlur?: (e: string) => void;
  openLinkInNewTab?: boolean;
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
    strikethrough: {
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
  contentClassName,
  displayMacrosButton = false,
  disabled = false,
  openLinkInNewTab = true
}: RichTextEditorProps): JSX.Element => {
  const { classes } = useStyles({ toolbarPositions });

  const hasInitialTextContent = initialEditorState
    ? JSON.parse(initialEditorState).root?.children.length > 0
    : false;

  const initialConfig = {
    editable,
    editorState: initialEditorState,
    namespace,
    nodes: [AutoLinkNode, LinkNode, HeadingNode, ListNode, ListItemNode],
    onError,
    theme: {
      link: classes.link,
      text: {
        bold: classes.bold,
        italic: classes.italic,
        strikethrough: classes.strikethrough,
        underline: classes.underline,
        underlineStrikethrough: classes.underlineStrikethrough
      }
    }
  };

  return (
    <LexicalComposer initialConfig={initialConfig}>
      <div className={classes.container}>
        <div className={classes.toolbar}>
          <ToolbarPlugin
            disabled={disabled}
            displayMacrosButton={displayMacrosButton}
            editable={editable}
          />
        </div>
        <div>
          <RichTextPlugin
            ErrorBoundary={LexicalErrorBoundary}
            contentEditable={
              <ContentEditable
                className={contentClassName || ''}
                disabled={disabled}
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
          <ListPlugin />
          <OnChangePlugin onChange={getEditorState} />
          <AutoCompleteLinkPlugin openLinkInNewTab={openLinkInNewTab} />
          <FloatingLinkEditorPlugin
            editable={editable}
            openLinkInNewTab={openLinkInNewTab}
          />
          {error && <Typography className={classes.error}>{error}</Typography>}
        </div>
      </div>
    </LexicalComposer>
  );
};
export default RichTextEditor;
