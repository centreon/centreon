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
  displayBlockButtons?: boolean;
  displayMacrosButton?: boolean;
  editable: boolean;
  editorState?: string;
  error?: string;
  getEditorState?: (editorState: EditorState, editor: LexicalEditor) => void;
  initialEditorState?: string;
  initialize?: (editor) => void;
  inputClassname?: string;
  minInputHeight?: number;
  namespace?: string;
  onBlur?: (e: string) => void;
  openLinkInNewTab?: boolean;
  placeholder?: string;
  resetEditorToInitialStateCondition?: () => boolean;
  toolbarClassName?: string;
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
    h1: {
      fontSize: theme.typography.h1.fontSize,
      fontWeight: theme.typography.h1.fontWeight,
      lineHeight: theme.typography.h1.lineHeight
    },
    h2: {
      fontSize: theme.typography.h2.fontSize,
      fontWeight: theme.typography.h2.fontWeight,
      lineHeight: theme.typography.h2.lineHeight
    },
    h3: {
      fontSize: theme.typography.h3.fontSize,
      fontWeight: theme.typography.h3.fontWeight,
      lineHeight: theme.typography.h3.lineHeight
    },
    h4: {
      fontSize: theme.typography.h4.fontSize,
      fontWeight: theme.typography.h4.fontWeight,
      lineHeight: theme.typography.h4.lineHeight
    },
    h5: {
      fontSize: theme.typography.h5.fontSize,
      fontWeight: theme.typography.h5.fontWeight,
      lineHeight: theme.typography.h5.lineHeight
    },
    h6: {
      fontSize: theme.typography.h6.fontSize,
      fontWeight: theme.typography.h6.fontWeight,
      lineHeight: theme.typography.h6.lineHeight
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
  openLinkInNewTab = true,
  initialize,
  displayBlockButtons = true,
  toolbarClassName
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
      heading: {
        h1: classes.h1,
        h2: classes.h2,
        h3: classes.h3,
        h4: classes.h4,
        h5: classes.h5,
        h6: classes.h6
      },
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
            className={toolbarClassName}
            disabled={disabled}
            displayBlockButtons={displayBlockButtons}
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
                initialize={initialize}
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
