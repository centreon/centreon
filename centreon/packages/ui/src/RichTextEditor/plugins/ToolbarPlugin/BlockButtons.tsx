import { useCallback, useEffect, useState } from 'react';

import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import {
  $createHeadingNode,
  $isHeadingNode,
  HeadingTagType
} from '@lexical/rich-text';
import { $setBlocksType } from '@lexical/selection';
import { $findMatchingParent, mergeRegister } from '@lexical/utils';
import {
  $createParagraphNode,
  $getSelection,
  $isRangeSelection,
  $isRootOrShadowRoot,
  COMMAND_PRIORITY_CRITICAL,
  SELECTION_CHANGE_COMMAND
} from 'lexical';
import { T, always, cond, equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import TextSizeIcon from '@mui/icons-material/TextFields';

import { Menu } from '../../../components';

import { useStyles } from './ToolbarPlugin.styles';

interface Props {
  disabled: boolean;
}

const blockTypeToBlockName = {
  h3: 'Huge',
  h5: 'Large',
  h6: 'Small',
  paragraph: 'Normal'
};

const blockTypes = ['h3', 'h5', 'paragraph', 'h6'];

const blockTypeOptions = blockTypes.map((blockType) => ({
  id: blockType,
  name: blockTypeToBlockName[blockType]
}));

const BlockButtons = ({ disabled }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [blockType, setBlockType] =
    useState<keyof typeof blockTypeToBlockName>('paragraph');

  const [editor] = useLexicalComposerContext();

  const formatParagraph = (): void => {
    editor.update(() => {
      const selection = $getSelection();
      $setBlocksType(selection, () => $createParagraphNode());
    });
  };

  const formatHeading = (headingSize: HeadingTagType): void => {
    if (blockType !== headingSize) {
      editor.update(() => {
        const selection = $getSelection();
        if ($isRangeSelection(selection)) {
          $setBlocksType(selection, () => $createHeadingNode(headingSize));
        }
      });
    }
  };

  const changeBlockType = (newBlockType): void => {
    const formatFunction = cond<Array<string>, (value?) => void>([
      [equals('h3'), always(() => formatHeading('h3'))],
      [equals('h5'), always(() => formatHeading('h5'))],
      [equals('h6'), always(() => formatHeading('h6'))],
      [T, always(formatParagraph)]
    ])(newBlockType || '');

    formatFunction();
  };

  const updateToolbar = useCallback(() => {
    const selection = $getSelection();
    const anchorNode = selection?.anchor.getNode();
    const element = equals(anchorNode?.getKey(), 'root')
      ? anchorNode
      : $findMatchingParent(anchorNode, (e) => {
          const parent = e.getParent();

          return parent !== null && $isRootOrShadowRoot(parent);
        }) || anchorNode?.getTopLevelElementOrThrow();

    const elementKey = element?.getKey();
    const elementDOM = editor.getElementByKey(elementKey);

    if (isNil(elementDOM)) {
      return;
    }

    const type = $isHeadingNode(element) ? element.getTag() : element.getType();

    if (type in blockTypeToBlockName) {
      setBlockType(type as keyof typeof blockTypeToBlockName);
    }
  }, [editor]);

  useEffect(() => {
    return editor.registerCommand(
      SELECTION_CHANGE_COMMAND,
      () => {
        updateToolbar();

        return false;
      },
      COMMAND_PRIORITY_CRITICAL
    );
  }, [editor, updateToolbar]);

  useEffect(() => {
    return mergeRegister(
      editor.registerUpdateListener(({ editorState }) => {
        editorState.read(() => {
          updateToolbar();
        });
      })
    );
  }, [editor, updateToolbar]);

  return (
    <Menu>
      <Menu.Button
        ariaLabel="block"
        className={classes.button}
        disabled={disabled}
      >
        <TextSizeIcon />
      </Menu.Button>
      <Menu.Items className={classes.menuItems}>
        {blockTypeOptions.map(({ id, name }) => (
          <Menu.Item
            isActive={equals(id, blockType)}
            key={name}
            onClick={() => changeBlockType(id)}
          >
            {t(name)}
          </Menu.Item>
        ))}
      </Menu.Items>
    </Menu>
  );
};

export default BlockButtons;
