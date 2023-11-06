import { useCallback, useEffect, useState } from 'react';

import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import {
  $createParagraphNode,
  $getSelection,
  $isRangeSelection,
  $isRootOrShadowRoot,
  COMMAND_PRIORITY_CRITICAL,
  SELECTION_CHANGE_COMMAND
} from 'lexical';
import {
  $createHeadingNode,
  $isHeadingNode,
  HeadingTagType
} from '@lexical/rich-text';
import { $setBlocksType } from '@lexical/selection';
import { T, always, cond, equals, isNil } from 'ramda';
import { $findMatchingParent, mergeRegister } from '@lexical/utils';

import TextSizeIcon from '@mui/icons-material/TextFields';

import { Menu } from '../../../components';

interface Props {
  disabled: boolean;
}

const blockTypeToBlockName = {
  h1: 'Huge',
  h4: 'Large',
  h6: 'Normal',
  paragraph: 'Small'
};

const blockTypes = ['h1', 'h4', 'h6', 'paragraph'];

const blockTypeOptions = blockTypes.map((blockType) => ({
  id: blockType,
  name: blockTypeToBlockName[blockType]
}));

const TextSizeButtons = ({ disabled }: Props): JSX.Element => {
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
      [equals('h1'), always(() => formatHeading('h1'))],
      [equals('h4'), always(() => formatHeading('h4'))],
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
      <Menu.Button disabled={disabled}>
        <TextSizeIcon />
      </Menu.Button>
      <Menu.Items>
        {blockTypeOptions.map(({ id, name }) => (
          <Menu.Item
            isActive={equals(id, blockType)}
            key={id}
            onClick={() => changeBlockType(id)}
          >
            {name}
          </Menu.Item>
        ))}
      </Menu.Items>
    </Menu>
  );
};

export default TextSizeButtons;
