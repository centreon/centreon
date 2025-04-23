import { useCallback, useEffect, useState } from 'react';

import {
  $isListNode,
  INSERT_ORDERED_LIST_COMMAND,
  INSERT_UNORDERED_LIST_COMMAND,
  ListNode,
  REMOVE_LIST_COMMAND
} from '@lexical/list';
import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import {
  $findMatchingParent,
  $getNearestNodeOfType,
  mergeRegister
} from '@lexical/utils';
import { $getSelection, $isRootOrShadowRoot } from 'lexical';
import { equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import UnorderedListIcon from '@mui/icons-material/FormatListBulleted';
import OrderedListIcon from '@mui/icons-material/FormatListNumbered';

import { Menu } from '../../../components';
import { labelOrderedList, labelUnorderedList } from '../../translatedLabels';

import { useStyles } from './ToolbarPlugin.styles';

const options = [
  {
    Icon: UnorderedListIcon,
    label: labelUnorderedList,
    value: 'bullet'
  },
  {
    Icon: OrderedListIcon,
    label: labelOrderedList,
    value: 'number'
  }
];

interface Props {
  disabled?: boolean;
}

const ListButton = ({ disabled }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [editor] = useLexicalComposerContext();

  const [elementList, setElementList] = useState(null);

  const formatBulletList = (): void => {
    if (elementList !== 'bullet') {
      editor.dispatchCommand(INSERT_UNORDERED_LIST_COMMAND, undefined);
    } else {
      editor.dispatchCommand(REMOVE_LIST_COMMAND, undefined);
    }
  };

  const formatNumberedList = (): void => {
    if (elementList !== 'number') {
      editor.dispatchCommand(INSERT_ORDERED_LIST_COMMAND, undefined);
    } else {
      editor.dispatchCommand(REMOVE_LIST_COMMAND, undefined);
    }
  };

  const formatList = (type): void => {
    if (equals(type, elementList)) {
      setElementList(null);
    }

    if (equals(type, 'bullet')) {
      formatBulletList();

      return;
    }
    formatNumberedList();
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
      setElementList(type);
    }
  }, [editor]);

  const selectedList = options.find(({ value }) => equals(value, elementList));

  useEffect(() => {
    return editor.registerUpdateListener(({ editorState }) => {
      editorState.read(() => {
        updateToolbar();
      });
    });
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
        ariaLabel="list"
        className={classes.button}
        disabled={disabled}
      >
        {selectedList ? <selectedList.Icon /> : <UnorderedListIcon />}
      </Menu.Button>
      <Menu.Items className={classes.menuItems}>
        <div className={classes.menu}>
          {options.map(({ Icon, value, label }) => (
            <Menu.Item
              isActive={equals(value, elementList)}
              key={value}
              onClick={() => formatList(value)}
            >
              <Icon aria-label={t(label)} />
            </Menu.Item>
          ))}
        </div>
      </Menu.Items>
    </Menu>
  );
};

export default ListButton;
