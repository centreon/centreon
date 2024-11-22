import { useCallback, useEffect, useState } from 'react';

import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import {
  $getSelection,
  $isElementNode,
  $isRangeSelection,
  ElementFormatType,
  FORMAT_ELEMENT_COMMAND
} from 'lexical';
import { equals } from 'ramda';

import FormatAlignCenterIcon from '@mui/icons-material/FormatAlignCenter';
import FormatAlignLeftIcon from '@mui/icons-material/FormatAlignLeft';
import FormatAlignRightIcon from '@mui/icons-material/FormatAlignRight';
import { SvgIconTypeMap } from '@mui/material';
import { OverridableComponent } from '@mui/material/OverridableComponent';

import { Menu } from '../../../components';
import { labelAlignPicker } from '../../translatedLabels';
import { getSelectedNode } from '../../utils/getSelectedNode';

import { useStyles } from './ToolbarPlugin.styles';

const formatOptions: Array<{
  Icon: OverridableComponent<SvgIconTypeMap<object, 'svg'>>;
  label: string;
  value: ElementFormatType;
}> = [
  {
    Icon: FormatAlignLeftIcon,
    label: 'Left',
    value: 'left'
  },
  {
    Icon: FormatAlignCenterIcon,
    label: 'Center',
    value: 'center'
  },
  {
    Icon: FormatAlignRightIcon,
    label: 'Right',
    value: 'right'
  }
];

interface Props {
  disabled: boolean;
}

const AlignPicker = ({ disabled }: Props): JSX.Element => {
  const { classes } = useStyles();

  const [elementFormat, setElementFormat] = useState<ElementFormatType>('left');

  const [editor] = useLexicalComposerContext();

  const dispatchAlignment = (alignment: ElementFormatType) => () => {
    editor.dispatchCommand(FORMAT_ELEMENT_COMMAND, alignment);
  };

  const updateElementFormat = useCallback(() => {
    const selection = $getSelection();

    if (!$isRangeSelection(selection)) {
      return;
    }

    const node = getSelectedNode(selection);
    const parent = node.getParent();

    setElementFormat(
      ($isElementNode(node) ? node.getFormatType() : parent?.getFormatType()) ||
        'left'
    );
  }, [editor]);

  const selectedFormat = formatOptions.find(({ value }) =>
    equals(value, elementFormat)
  );

  useEffect(() => {
    return editor.registerUpdateListener(({ editorState }) => {
      editorState.read(() => {
        updateElementFormat();
      });
    });
  }, [editor, updateElementFormat]);

  return (
    <Menu>
      <Menu.Button
        ariaLabel={labelAlignPicker}
        className={classes.button}
        disabled={disabled}
      >
        {selectedFormat && <selectedFormat.Icon />}
      </Menu.Button>
      <Menu.Items className={classes.menuItems}>
        <div className={classes.menu}>
          {formatOptions.map(({ Icon, value, label }) => (
            <Menu.Item
              isActive={equals(value, elementFormat)}
              key={value}
              onClick={dispatchAlignment(value)}
            >
              <Icon aria-label={label} />
            </Menu.Item>
          ))}
        </div>
      </Menu.Items>
    </Menu>
  );
};

export default AlignPicker;
