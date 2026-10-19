import RichTextEditor, { type RichTextEditorProps } from './RichTextEditor';

type Props = Pick<
  RichTextEditorProps,
  | 'disabled'
  | 'displayMacrosButton'
  | 'editable'
  | 'initialEditorState'
  | 'minInputHeight'
  | 'namespace'
  | 'openLinkInNewTab'
  | 'placeholder'
>;

export const Default = (props: Props): JSX.Element => (
  <RichTextEditor {...props} />
);
