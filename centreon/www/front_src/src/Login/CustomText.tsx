import { RichTextEditor } from '@centreon/ui';

import { LoginPageCustomisation } from './models';

interface Props {
  loginPageCustomisation: LoginPageCustomisation;
}

const CustomText = ({ loginPageCustomisation }: Props): JSX.Element => (
  <RichTextEditor
    editable={false}
    editorState={loginPageCustomisation.customText || undefined}
    minInputHeight={0}
    namespace={`Preview${loginPageCustomisation.textPosition}`}
  />
);

export default CustomText;
