import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import { LexicalCommand, createCommand } from 'lexical';

import MacrosIcon from '@mui/icons-material/TerminalOutlined';

import { Menu } from '../../../components';

import { useStyles } from './ToolbarPlugin.styles';

const LowPriority = 1;

interface Props {
  disabled: boolean;
}

export const standardMacros = [
  '{{SHORTDATETIME}}',
  '{{LONGDATETIME}}',
  '{{ALIAS}}',
  '{{ID}}',
  '{{NAME}}',
  '{{OUTPUT}}',
  '{{STATE}}'
];

const MacrosButton = ({ disabled }: Props): JSX.Element => {
  const { classes } = useStyles();

  const [editor] = useLexicalComposerContext();

  const insertMacrosCommand: LexicalCommand<string> = createCommand();

  const onClick = (macro): void => {
    editor.dispatchCommand(insertMacrosCommand, macro);
  };

  editor.registerCommand(
    insertMacrosCommand,
    (payload: string) => {
      if (!editor) {
        return false;
      }

      document.execCommand('insertText', false, payload);

      return true;
    },
    LowPriority
  );

  return (
    <Menu>
      <Menu.Button
        ariaLabel="Macros"
        className={classes.button}
        disabled={disabled}
      >
        <MacrosIcon />
      </Menu.Button>
      <Menu.Items className={classes.menuItems}>
        {standardMacros.map((name) => (
          <Menu.Item
            className={classes.menuItem}
            key={name}
            onClick={(): void => onClick(name)}
          >
            {name}
          </Menu.Item>
        ))}
      </Menu.Items>
    </Menu>
  );
};

export default MacrosButton;
