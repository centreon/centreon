import { useCallback, useEffect, useMemo, useState } from 'react';

import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import { mergeRegister } from '@lexical/utils';
import {
  $getSelection,
  $isRangeSelection,
  FORMAT_TEXT_COMMAND,
  SELECTION_CHANGE_COMMAND,
  TextFormatType
} from 'lexical';

import FormatBoldIcon from '@mui/icons-material/FormatBold';
import FormatTextIcon from '@mui/icons-material/FormatColorText';
import FormatItalicIcon from '@mui/icons-material/FormatItalic';
import FormatUnderlinedIcon from '@mui/icons-material/FormatUnderlined';
import StrikethroughSIcon from '@mui/icons-material/StrikethroughS';

import { Menu } from '../../../components';

import { useStyles } from './ToolbarPlugin.styles';

const LowPriority = 1;

interface Props {
  disabled: boolean;
}

const FormatButtons = ({ disabled }: Props): JSX.Element => {
  const { classes } = useStyles();

  const [isBold, setIsBold] = useState(false);
  const [isItalic, setIsItalic] = useState(false);
  const [isUnderline, setIsUnderline] = useState(false);
  const [isStrikeThrough, setIsStrikeThrough] = useState(false);

  const [editor] = useLexicalComposerContext();

  const updateToolbar = useCallback((): void => {
    const selection = $getSelection();
    if (!$isRangeSelection(selection)) {
      return;
    }
    setIsBold(selection.hasFormat('bold'));
    setIsItalic(selection.hasFormat('italic'));
    setIsUnderline(selection.hasFormat('underline'));
    setIsStrikeThrough(selection.hasFormat('strikethrough'));
  }, [editor]);

  useEffect(() => {
    return mergeRegister(
      editor.registerCommand(
        SELECTION_CHANGE_COMMAND,
        () => {
          updateToolbar();

          return false;
        },
        LowPriority
      )
    );
  }, [editor, updateToolbar]);

  const toggleTextFormat = (textFormat: TextFormatType) => (): void => {
    editor.dispatchCommand(FORMAT_TEXT_COMMAND, textFormat);
  };

  const formatButtons = useMemo(
    () => [
      {
        Icon: FormatBoldIcon,
        isSelected: isBold,
        onClickFunction: toggleTextFormat('bold'),
        type: 'bold'
      },
      {
        Icon: FormatItalicIcon,
        isSelected: isItalic,
        onClickFunction: toggleTextFormat('italic'),
        type: 'italic'
      },
      {
        Icon: FormatUnderlinedIcon,
        isSelected: isUnderline,
        onClickFunction: toggleTextFormat('underline'),
        type: 'underline'
      },
      {
        Icon: StrikethroughSIcon,
        isSelected: isStrikeThrough,
        onClickFunction: toggleTextFormat('strikethrough'),
        type: 'strikethrough'
      }
    ],
    [isBold, isItalic, isUnderline, isStrikeThrough]
  );

  return (
    <Menu>
      <Menu.Button
        ariaLabel="format"
        className={classes.button}
        disabled={disabled}
      >
        <FormatTextIcon />
      </Menu.Button>
      <Menu.Items className={classes.menuItems}>
        <div className={classes.menu}>
          {formatButtons.map(({ Icon, onClickFunction, isSelected, type }) => (
            <Menu.Item
              isActive={isSelected}
              key={type}
              onClick={onClickFunction}
            >
              <Icon aria-label={type} />
            </Menu.Item>
          ))}
        </div>
      </Menu.Items>
    </Menu>
  );
};

export default FormatButtons;
