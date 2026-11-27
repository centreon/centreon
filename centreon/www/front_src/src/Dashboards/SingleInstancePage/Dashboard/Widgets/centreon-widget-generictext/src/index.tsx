import { RichTextEditor } from '@centreon/ui';

import { makeStyles } from 'tss-react/mui';

import { isRichTextEditorEmpty } from '../../../utils';

interface Props {
  panelOptions?: {
    description?: {
      content?: string;
      enabled: boolean;
    };
  };
}

const useStyles = makeStyles()(() => ({
  content: {
    marginTop: '-8px'
  }
}));

const GenericText = ({ panelOptions }: Props): JSX.Element | null => {
  const { classes } = useStyles();

  const displayDescription =
    panelOptions?.description?.enabled &&
    panelOptions?.description?.content &&
    !isRichTextEditorEmpty(panelOptions?.description?.content);

  if (!displayDescription) {
    return null;
  }

  return (
    <RichTextEditor
      contentClassName={classes.content}
      disabled
      editable={false}
      editorState={panelOptions?.description?.content}
    />
  );
};

export default GenericText;
