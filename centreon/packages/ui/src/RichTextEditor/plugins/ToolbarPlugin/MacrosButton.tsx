import { useState } from 'react';

import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import { createCommand, LexicalCommand } from 'lexical';

import MacrosIcon from '@mui/icons-material/TerminalOutlined';

import { ActionsList, IconButton, PopoverMenu } from '../../..';

import { useStyles } from '.';

const LowPriority = 1;

const MacrosButton = (): JSX.Element => {
  const { classes } = useStyles();
  const [arMacrosDiplayed, setAreMacrosDiplayed] = useState(false);

  const [editor] = useLexicalComposerContext();

  const INSERT_MACROS_COMMAND: LexicalCommand<string> = createCommand();

  const onClick = (e): void => {
    const macro = e.target.id || e.target.textContent.trim();

    editor.dispatchCommand(INSERT_MACROS_COMMAND, macro);
  };

  editor.registerCommand(
    INSERT_MACROS_COMMAND,
    (payload: string) => {
      if (!editor) return false;

      document.execCommand('insertText', false, payload);

      return true;
    },
    LowPriority
  );

  const displayMacros = (): void => {
    setAreMacrosDiplayed(true);
  };

  const actions = [
    { label: '{{SHORTDATETIME}}', onClick },
    { label: '{{LONGDATETIME}}', onClick },
    { label: '{{ALIAS}}', onClick },
    { label: '{{ID}}', onClick },
    { label: '{{NAME}}', onClick },
    { label: '{{OUTPUT}}', onClick },
    { label: '{{STATE}}', onClick },
    { label: '{{STATETYPE}}', onClick }
  ];

  return (
    <PopoverMenu
      icon={
        <IconButton
          ariaLabel="Macros"
          className={classes.macrosButton}
          title="Macros"
          tooltipPlacement="top"
          onClick={displayMacros}
        >
          <MacrosIcon />
        </IconButton>
      }
      // title="Macros"
    >
      {(): JSX.Element => (
        <div>{arMacrosDiplayed && <ActionsList actions={actions} />}</div>
      )}
    </PopoverMenu>
  );
};

export default MacrosButton;
