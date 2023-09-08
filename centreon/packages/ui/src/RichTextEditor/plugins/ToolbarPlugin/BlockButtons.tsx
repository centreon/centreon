import { useCallback, useEffect, useState } from 'react';

import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import {
  $isListNode,
  INSERT_ORDERED_LIST_COMMAND,
  INSERT_UNORDERED_LIST_COMMAND,
  ListNode,
  REMOVE_LIST_COMMAND
} from '@lexical/list';
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
import {
  $findMatchingParent,
  $getNearestNodeOfType,
  mergeRegister
} from '@lexical/utils';

import { SingleAutocompleteField } from '../../..';

import { useBlockButtonsStyles } from './ToolbarPlugin.styles';

interface Props {
  disabled: boolean;
}

const blockTypeToBlockName = {
  bullet: 'Bullet List',
  h1: 'Heading 1',
  h2: 'Heading 2',
  h3: 'Heading 3',
  h4: 'Heading 4',
  h5: 'Heading 5',
  h6: 'Heading 6',
  number: 'Number List',
  paragraph: 'Normal'
};

const blockTypes = [
  'h1',
  'h2',
  'h3',
  'h4',
  'h5',
  'h6',
  'bullet',
  'number',
  'paragraph'
];

const blockTypeOptions = blockTypes.map((blockType) => ({
  id: blockType,
  name: blockTypeToBlockName[blockType]
}));

const BlockButtons = ({ disabled }: Props): JSX.Element => {
  const { classes } = useBlockButtonsStyles();

  const [blockType, setBlockType] =
    useState<keyof typeof blockTypeToBlockName>('paragraph');

  const [editor] = useLexicalComposerContext();

  const formatParagraph = (): void => {
    editor.update(() => {
      const selection = $getSelection();
      $setBlocksType(selection, () => $createParagraphNode());
    });
  };

  const formatBulletList = (): void => {
    if (blockType !== 'bullet') {
      editor.dispatchCommand(INSERT_UNORDERED_LIST_COMMAND, undefined);
    } else {
      editor.dispatchCommand(REMOVE_LIST_COMMAND, undefined);
    }
  };

  const formatNumberedList = (): void => {
    if (blockType !== 'number') {
      editor.dispatchCommand(INSERT_ORDERED_LIST_COMMAND, undefined);
    } else {
      editor.dispatchCommand(REMOVE_LIST_COMMAND, undefined);
    }
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

  const changeBlockType = (_, newBlockType): void => {
    const formatFunction = cond<Array<string>, (value?) => void>([
      [equals('bullet'), always(formatBulletList)],
      [equals('number'), always(formatNumberedList)],
      [equals('h1'), always(() => formatHeading('h1'))],
      [equals('h2'), always(() => formatHeading('h2'))],
      [equals('h3'), always(() => formatHeading('h3'))],
      [equals('h4'), always(() => formatHeading('h4'))],
      [equals('h5'), always(() => formatHeading('h5'))],
      [equals('h6'), always(() => formatHeading('h6'))],
      [T, always(formatParagraph)]
    ])(newBlockType?.id || '');

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

    if ($isListNode(element)) {
      const parentList = $getNearestNodeOfType(anchorNode, ListNode);
      const type = parentList
        ? parentList.getListType()
        : element.getListType();
      setBlockType(type);

      return;
    }
    const type = $isHeadingNode(element) ? element.getTag() : element.getType();
    if (type in blockTypeToBlockName) {
      setBlockType(type as keyof typeof blockTypeToBlockName);
    }
  }, [editor]);

  const value = blockTypeOptions.find((option) => option.id === blockType);

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
    <SingleAutocompleteField
      className={classes.autocomplete}
      dataTestId="Block type"
      disabled={disabled}
      label=""
      options={blockTypeOptions}
      value={value}
      onChange={changeBlockType}
    />
  );
};

export default BlockButtons;
