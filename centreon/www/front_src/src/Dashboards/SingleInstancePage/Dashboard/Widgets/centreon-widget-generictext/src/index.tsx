import { RichTextEditor } from "@centreon/ui";
import { isRichTextEditorEmpty } from "../../../utils";

interface Props {
  panelOptions?: {
    description?: {
      content?: string;
      enabled: boolean;
    }
  }
}

const GenericText = ({ panelOptions }: Props): JSX.Element | null => {
  const displayDescription =
    panelOptions?.description?.enabled &&
    panelOptions?.description?.content &&
    !isRichTextEditorEmpty(panelOptions?.description?.content);

  if (!displayDescription) {
    return null;
  }

  return <RichTextEditor
    disabled
    editable={false}
    editorState={
      panelOptions?.description?.content || undefined
    }
  />
};

export default GenericText;
